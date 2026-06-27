<?php

namespace App\Console\Commands;

use App\Mail\ReviewAutoSubmittedMail;
use App\Mail\ReviewCompletedMail;
use App\Mail\ReviewDeadlineReminderMail;
use App\Models\DefenseAttempt;
use App\Models\Paper;
use App\Models\Review;
use App\Services\ReviewScoringService;
use App\Support\Notify;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('reviews:process-deadlines')]
#[Description('Auto-submit completed review drafts and remind incomplete reviewers after score deadlines.')]
class ProcessReviewDeadlines extends Command
{
    public function __construct(private readonly ReviewScoringService $reviewScoringService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $autoSubmitted = 0;
        $reminded = 0;

        DefenseAttempt::query()
            ->whereNotNull('score_deadline_at')
            ->where('score_deadline_at', '<=', now())
            ->with([
                'period.rubric',
                'team.subject.teacher',
                'team.subject.rubric',
                'activeReviewerAssignments.reviewer',
                'papers.subject',
                'papers.reviews.reviewer',
            ])
            ->chunkById(50, function ($attempts) use (&$autoSubmitted, &$reminded): void {
                foreach ($attempts as $attempt) {
                    $paper = $attempt->papers->sortByDesc('id')->first();

                    if (! $paper instanceof Paper) {
                        continue;
                    }

                    $paper->setRelation('defenseAttempt', $attempt);
                    $paper->setRelation('subject', $paper->subject ?? $attempt->team->subject);

                    $structure = ($attempt->period?->rubric ?? $attempt->team?->subject?->rubric)?->structure_json ?? [];

                    foreach ($attempt->activeReviewerAssignments as $assignment) {
                        if (! $assignment->reviewer) {
                            continue;
                        }

                        $review = $paper->reviews->firstWhere('reviewer_id', $assignment->reviewer_id);

                        if ($review?->is_submitted) {
                            continue;
                        }

                        if ($review instanceof Review && $this->reviewScoringService->hasCompletedRequiredScores($review, $structure)) {
                            $this->autoSubmitReview($review, $paper);
                            $autoSubmitted++;

                            continue;
                        }

                        if ($assignment->score_deadline_reminded_at === null) {
                            Mail::to($assignment->reviewer)->queue(new ReviewDeadlineReminderMail($assignment, $paper));
                            Notify::send(
                                $assignment->reviewer,
                                'Review deadline missed',
                                'Your review for '.$paper->team->name.' is incomplete. Please submit the required scores.',
                                route('reviews.create', $paper),
                                'review',
                            );

                            $assignment->forceFill(['score_deadline_reminded_at' => now()])->save();
                            $reminded++;
                        }
                    }
                }
            });

        $this->info("Completed drafts auto-submitted: {$autoSubmitted}");
        $this->info("Incomplete reviewers reminded: {$reminded}");

        return self::SUCCESS;
    }

    private function autoSubmitReview(Review $review, Paper $paper): void
    {
        $review->forceFill([
            'is_submitted' => true,
            'locked_at' => now(),
            'auto_submitted_at' => now(),
        ])->save();

        $review->loadMissing('reviewer');
        $review->setRelation('paper', $paper);

        $this->reviewScoringService->recalculateFinalScore($paper);

        if ($review->reviewer) {
            Mail::to($review->reviewer)->queue(new ReviewAutoSubmittedMail($review));
            Notify::send(
                $review->reviewer,
                'Review auto-submitted',
                'Your completed draft for '.$paper->team->name.' was submitted automatically at the deadline.',
                route('reviews.show', $review),
                'review',
            );
        }

        $teacher = $paper->subject?->teacher;
        if ($teacher) {
            Mail::to($teacher)->queue(new ReviewCompletedMail($review));
            Notify::send(
                $teacher,
                'Review auto-submitted',
                ($review->reviewer?->name ?? 'A reviewer').' had a completed draft auto-submitted for '.$paper->team->name.'.',
                route('papers.show', $paper),
                'review',
            );
        }
    }
}
