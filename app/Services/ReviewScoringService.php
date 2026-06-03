<?php

namespace App\Services;

use App\Models\DefenseAttempt;
use App\Models\Paper;
use App\Models\Review;

class ReviewScoringService
{
    /**
     * Consistent display order for scoring roles:
     * 1. FYP Instructor  2. Advisor  3. Academic examiner
     * 4. Technical examiner  5. Custom roles (alphabetical by label).
     */
    public function roleSortKey(?string $committeeRole): string
    {
        $normalized = str_replace('_', ' ', strtolower(trim((string) $committeeRole)));

        $rank = match ($normalized) {
            'fyp instructor' => 0,
            'advisor' => 1,
            'academic examiner' => 2,
            'technical examiner' => 3,
            default => 4,
        };

        return $rank.'|'.$normalized;
    }

    /**
     * Summarise the scoring responsibilities for a defense session: how many active
     * responsibilities are required, how many are submitted, which roles are still
     * outstanding, and whether the result is ready to release.
     *
     * @return array{required: int, submitted: int, ready: bool, missing_roles: array<int, string>}
     */
    public function responsibilitySummary(DefenseAttempt $attempt, ?Paper $paper = null): array
    {
        $attempt->loadMissing('activeReviewerAssignments');

        $responsibilities = $attempt->activeReviewerAssignments
            ->where('excluded_from_calculation', false)
            ->values();

        $paper ??= $attempt->papers()->latest('id')->first();

        $submittedAssignmentIds = $paper
            ? $paper->reviews()
                ->where('is_submitted', true)
                ->whereNotNull('defense_attempt_reviewer_id')
                ->pluck('defense_attempt_reviewer_id')
                ->all()
            : [];

        $submitted = $responsibilities
            ->filter(fn ($assignment) => in_array($assignment->id, $submittedAssignmentIds, true))
            ->count();

        $missingRoles = $responsibilities
            ->reject(fn ($assignment) => in_array($assignment->id, $submittedAssignmentIds, true))
            ->map(fn ($assignment) => (string) $assignment->committee_role)
            ->values()
            ->all();

        $required = $responsibilities->count();

        return [
            'required' => $required,
            'submitted' => $submitted,
            'ready' => $required > 0 && $submitted === $required,
            'missing_roles' => $missingRoles,
        ];
    }

    /**
     * @param  array<int, array{criteria?: string, max_score?: int|float, weight?: float|int}>  $structure
     */
    public function hasCompletedRequiredScores(Review $review, array $structure): bool
    {
        if ($structure === []) {
            return false;
        }

        $scores = collect($review->scores_json ?? [])
            ->filter(fn (array $score): bool => isset($score['criteria']))
            ->keyBy(fn (array $score): string => (string) $score['criteria']);

        foreach ($structure as $criterion) {
            $criteria = (string) ($criterion['criteria'] ?? '');
            if ($criteria === '' || ! $scores->has($criteria)) {
                return false;
            }

            $score = $scores->get($criteria)['score'] ?? null;
            if (! is_numeric($score) || (float) $score <= 0) {
                return false;
            }
        }

        return true;
    }

    public function recalculateFinalScore(Paper $paper): void
    {
        $paper->loadMissing('subject.rubric', 'defenseAttempt.period.rubric');
        $submittedReviews = $paper->reviews()
            ->where('is_submitted', true)
            ->where(function ($query) {
                $query->whereNull('defense_attempt_reviewer_id')
                    ->orWhereHas('reviewerAssignment', function ($assignment) {
                        $assignment->where('status', 'active')
                            ->where('excluded_from_calculation', false);
                    });
            })
            ->get();

        if ($submittedReviews->isEmpty()) {
            return;
        }

        $structure = ($paper->defenseAttempt?->period?->rubric ?? $paper->subject?->rubric)?->structure_json ?? [];
        $weights = $this->normalizedWeightsByCriteria($structure);

        $totalScores = $submittedReviews->map(function (Review $review) use ($weights) {
            $scores = collect($review->scores_json ?? []);
            $hasWeightedScores = false;

            $weightedTotal = $scores->sum(function (array $score) use ($weights, &$hasWeightedScores) {
                $criteria = (string) ($score['criteria'] ?? '');
                $rawScore = (float) ($score['score'] ?? 0);
                $weight = $weights[$criteria]['weight'] ?? null;
                $maxScore = $weights[$criteria]['max_score'] ?? null;

                if ($criteria === '' || $weight === null || $maxScore === null || $maxScore <= 0) {
                    return 0;
                }

                $hasWeightedScores = true;
                $normalized = min($rawScore, $maxScore) / $maxScore;

                return $normalized * $weight;
            });

            if ($hasWeightedScores) {
                return $weightedTotal;
            }

            return $scores->sum(fn (array $score) => (float) ($score['score'] ?? 0));
        });

        $score = round($totalScores->avg(), 2);

        $paper->update([
            'final_score' => $score,
        ]);

        $paper->defenseAttempt?->update(['final_score' => $score]);
    }

    /**
     * @param  array<int, array{criteria?: string, max_score?: int|float, weight?: float|int}>  $structure
     * @return array<string, array{weight: float, max_score: int}>
     */
    private function normalizedWeightsByCriteria(array $structure): array
    {
        if ($structure === []) {
            return [];
        }

        $total = array_sum(array_map(fn ($item) => (float) ($item['weight'] ?? 0), $structure));
        $count = count($structure);
        $weights = [];

        if ($total <= 0) {
            $equalWeight = round(100 / $count, 2);
            $remaining = 100.0;

            foreach ($structure as $index => $item) {
                $weight = $index === $count - 1 ? round($remaining, 2) : $equalWeight;
                $remaining -= $weight;
                $weights[(string) $item['criteria']] = [
                    'weight' => $weight,
                    'max_score' => (int) $item['max_score'],
                ];
            }

            return $weights;
        }

        $remaining = 100.0;

        foreach ($structure as $index => $item) {
            $weight = $index === $count - 1
                ? round($remaining, 2)
                : round(((float) ($item['weight'] ?? 0) / $total) * 100, 2);
            $remaining -= $weight;

            $weights[(string) $item['criteria']] = [
                'weight' => $weight,
                'max_score' => (int) $item['max_score'],
            ];
        }

        return $weights;
    }
}
