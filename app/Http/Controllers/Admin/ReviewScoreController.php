<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewCorrectionLog;
use App\Services\ReviewScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewScoreController extends Controller
{
    public function __construct(private readonly ReviewScoringService $reviewScoringService) {}

    public function update(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'scores_json' => ['required', 'array'],
            'scores_json.*.criteria' => ['required', 'string'],
            'scores_json.*.score' => ['required', 'numeric', 'min:0'],
            'scores_json.*.max_score' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'scores_json.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores_json.*.comment' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        foreach ($validated['scores_json'] as $entry) {
            $score = (float) ($entry['score'] ?? 0);
            $maxScore = (float) ($entry['max_score'] ?? 0);

            if ($maxScore > 0 && $score > $maxScore) {
                return back()->withErrors([
                    'scores_json' => 'One score is higher than its maximum allowed score.',
                ]);
            }
        }

        $review->loadMissing('paper');
        $scoresBefore = $review->scores_json;
        $commentBefore = $review->comment;
        $allowedTags = '<p><br><strong><em><ul><ol><li><u><s><blockquote>';

        $scoresAfter = collect($validated['scores_json'])
            ->map(function (array $entry) use ($allowedTags): array {
                if (isset($entry['comment']) && $entry['comment'] !== null) {
                    $entry['comment'] = strip_tags((string) $entry['comment'], $allowedTags);
                }

                return $entry;
            })
            ->values()
            ->all();

        $commentAfter = $validated['comment'] ?? null;
        if ($commentAfter !== null) {
            $commentAfter = strip_tags($commentAfter, '<p><br><strong><em><ul><ol><li><u><s><h1><h2><h3><h4><blockquote>');
        }

        $review->update([
            'scores_json' => $scoresAfter,
            'comment' => $commentAfter,
        ]);

        ReviewCorrectionLog::create([
            'review_id' => $review->id,
            'paper_id' => $review->paper_id,
            'defense_attempt_id' => $review->defense_attempt_id,
            'corrected_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'scores_before' => $scoresBefore,
            'scores_after' => $scoresAfter,
            'comment_before' => $commentBefore,
            'comment_after' => $commentAfter,
        ]);

        if ($review->paper) {
            $this->reviewScoringService->recalculateFinalScore($review->paper);
        }

        return back()->with('success', 'Reviewer score correction saved and audit logged.');
    }
}
