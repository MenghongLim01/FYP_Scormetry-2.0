<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Mail\ReviewCompletedMail;
use App\Models\Paper;
use App\Models\Review;
use App\Models\ReviewUnlockLog;
use App\Models\SubjectMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function create(Request $request, Paper $paper): Response
    {
        $user = $request->user();
        $paper->load(['subject', 'team.members', 'defenseAttempt.activeReviewerAssignments']);
        $isTeacher = $paper->subject->teacher_id === $user->id;
        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        abort_unless($user->isAdmin() || $isTeacher || ($isReviewer && $this->reviewerCanAccessPaper($paper, $user)), 403);

        $paper->load(['subject.rubric', 'defenseAttempt.period.rubric', 'reviews.reviewer']);

        $existingReview = $paper->reviews->firstWhere('reviewer_id', $user->id);

        return Inertia::render('reviews/Create', [
            'paper' => $paper,
            'paperPdfUrl' => route('papers.pdf', $paper),
            'rubricPdfUrl' => ($paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                ? route('rubrics.pdf', $paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                : null,
            'existingReview' => $existingReview ? [
                'id'          => $existingReview->id,
                'reviewer_id' => $existingReview->reviewer_id,
                'scores_json' => $existingReview->scores_json,
                'comment'     => $existingReview->comment,
                'locked_at'   => $existingReview->locked_at?->toISOString(),
            ] : null,
        ]);
    }

    public function store(StoreReviewRequest $request, Paper $paper): RedirectResponse
    {
        // Enforce the score submission deadline
        $paper->loadMissing('team', 'defenseAttempt.activeReviewerAssignments', 'subject');
        $scoreDeadline = $paper->defenseAttempt?->score_deadline_at ?? $paper->team?->score_deadline_at;

        if ($scoreDeadline && $scoreDeadline->isPast()) {
            return back()->withErrors([
                'deadline' => 'The scoring deadline has passed. Contact the subject teacher to extend it.',
            ]);
        }

        $assignment = $paper->defenseAttempt?->activeReviewerAssignments
            ->firstWhere('reviewer_id', $request->user()->id);

        $committeeRole = $assignment?->committee_role
            ?? SubjectMember::where('subject_id', $paper->subject_id)
            ->where('user_id', $request->user()->id)
            ->where('role', '!=', 'student')
            ->value('role_label')
            ?? SubjectMember::where('subject_id', $paper->subject_id)
                ->where('user_id', $request->user()->id)
                ->where('role', '!=', 'student')
                ->value('role');

        $comment = $request->validated('comment');
        if ($comment !== null) {
            $comment = strip_tags($comment, '<p><br><strong><em><ul><ol><li><u><s><h1><h2><h3><h4><blockquote>');
        }

        // Sanitize per-criterion inline comments the same way as the main comment
        $allowedTags = '<p><br><strong><em><ul><ol><li><u><s><blockquote>';
        $scoresJson = collect($request->validated('scores_json'))
            ->map(function (array $entry) use ($allowedTags): array {
                if (isset($entry['comment']) && $entry['comment'] !== null) {
                    $entry['comment'] = strip_tags((string) $entry['comment'], $allowedTags);
                }
                return $entry;
            })
            ->all();

        $review = $paper->reviews()->updateOrCreate(
            ['reviewer_id' => $request->user()->id],
            [
                'defense_attempt_id' => $paper->defense_attempt_id,
                'defense_attempt_reviewer_id' => $assignment?->id,
                'committee_role' => $committeeRole,
                'scores_json' => $scoresJson,
                'comment' => $comment,
                'is_submitted' => true,
                'locked_at' => now(),
            ],
        );

        $this->recalculateFinalScore($paper);

        $review->load('paper.subject.teacher', 'reviewer');
        if ($paper->subject->teacher) {
            Mail::to($paper->subject->teacher)->send(new ReviewCompletedMail($review));
        }

        return to_route('papers.show', $paper)
            ->with('success', 'Review submitted successfully.');
    }

    public function show(Request $request, Review $review): Response
    {
        $user = $request->user();
        $review->load(['paper.team.members', 'paper.subject', 'paper.defenseAttempt.activeReviewerAssignments', 'reviewer']);

        $isTeamMember = $review->paper->team && $review->paper->team->members->contains('id', $user->id);
        $isReviewerOfSubject = $review->paper->subject->reviewers()->where('users.id', $user->id)->exists();

        $canAccess = $user->isAdmin()
            || $review->reviewer_id === $user->id
            || $review->paper->subject->teacher_id === $user->id
            || ($isReviewerOfSubject && $this->reviewerCanAccessPaper($review->paper, $user))
            // Students can only see reviews after the paper is published.
            || ($isTeamMember && ! $isReviewerOfSubject && $review->paper->visibility_status === 'published');
        abort_unless($canAccess, 403);

        return Inertia::render('reviews/Show', [
            'review' => $review,
        ]);
    }

    public function unlock(Request $request, Review $review): RedirectResponse
    {
        $user = $request->user();
        $review->load(['paper.subject', 'paper.team']);

        $subject = $review->paper->subject;
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);
        abort_unless($review->isLocked(), 422, 'Review is not locked.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $review->update([
            'locked_at'     => null,
            'unlocked_at'   => now(),
            'unlock_reason' => $validated['reason'],
            'unlocked_by'   => $user->id,
        ]);

        ReviewUnlockLog::create([
            'review_id'   => $review->id,
            'team_id'     => $review->paper->team_id,
            'judge_id'    => $review->reviewer_id,
            'unlocked_by' => $user->id,
            'reason'      => $validated['reason'],
        ]);

        return back()->with('success', 'Review unlocked. The judge can now edit their submission.');
    }

    private function recalculateFinalScore(Paper $paper): void
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
     * @param  array<int, array{criteria: string, max_score: int, weight: float|int}>  $structure
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

    private function reviewerCanAccessPaper(Paper $paper, \App\Models\User $user): bool
    {
        if ($paper->defenseAttempt) {
            return $paper->defenseAttempt->activeReviewerAssignments->contains('reviewer_id', $user->id);
        }

        return $paper->team && $paper->team->members->contains('id', $user->id);
    }
}
