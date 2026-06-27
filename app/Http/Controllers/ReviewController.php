<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Mail\ReviewCompletedMail;
use App\Models\Paper;
use App\Models\Review;
use App\Models\ReviewUnlockLog;
use App\Models\SubjectMember;
use App\Services\ReviewScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewScoringService $reviewScoringService) {}

    public function create(Request $request, Paper $paper): Response
    {
        $user = $request->user();
        $paper->load(['subject', 'team.members', 'defenseAttempt.activeReviewerAssignments']);
        $isTeacher = $paper->subject->teacher_id === $user->id;
        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        abort_unless($user->isAdmin() || $isTeacher || ($isReviewer && $this->reviewerCanAccessPaper($paper, $user)), 403);
        abort_unless($paper->isTurnedIn(), 403, 'This document has not been turned in yet.');

        $paper->load(['subject.rubric', 'defenseAttempt.period.rubric', 'reviews.reviewer']);

        // The current user's scoring responsibilities (one per assigned role) for
        // this defense session. A judge holding two roles has two responsibilities,
        // each scored and submitted separately.
        $myAssignments = ($paper->defenseAttempt?->activeReviewerAssignments ?? collect())
            ->where('reviewer_id', $user->id)
            ->sortBy(fn ($a) => $this->reviewScoringService->roleSortKey($a->committee_role))
            ->values();

        $reviewsByAssignment = $paper->reviews->keyBy('defense_attempt_reviewer_id');

        $responsibilities = $myAssignments->map(function ($assignment) use ($reviewsByAssignment) {
            $review = $reviewsByAssignment->get($assignment->id);

            return [
                'assignment_id'  => $assignment->id,
                'committee_role' => $assignment->committee_role,
                'has_review'     => $review !== null,
                'is_submitted'   => (bool) $review?->is_submitted,
                'locked'         => $review?->locked_at !== null,
            ];
        })->values();

        // Which responsibility is being scored on this visit? Defaults to the only
        // one when a judge holds a single role; otherwise the page asks them to pick.
        $selectedAssignmentId = $request->integer('assignment') ?: null;
        $selected = $selectedAssignmentId
            ? $myAssignments->firstWhere('id', $selectedAssignmentId)
            : ($myAssignments->count() === 1 ? $myAssignments->first() : null);

        $existingReview = $selected
            ? $reviewsByAssignment->get($selected->id)
            : $paper->reviews->firstWhere(fn ($r) => $r->reviewer_id === $user->id && $r->defense_attempt_reviewer_id === null);

        // Privacy: a scoring judge must not see other judges' feedback.
        if (! $user->isAdmin() && ! $isTeacher) {
            $paper->setRelation('reviews', collect());
        }

        return Inertia::render('reviews/Create', [
            'paper' => $paper,
            'paperPdfUrl' => route('papers.pdf', $paper),
            'rubricPdfUrl' => ($paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                ? route('rubrics.pdf', $paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                : null,
            'responsibilities' => $responsibilities,
            'selectedAssignmentId' => $selected?->id,
            'selectedRole' => $selected?->committee_role,
            'existingReview' => $existingReview ? [
                'id'          => $existingReview->id,
                'reviewer_id' => $existingReview->reviewer_id,
                'defense_attempt_reviewer_id' => $existingReview->defense_attempt_reviewer_id,
                'scores_json' => $existingReview->scores_json,
                'comment'     => $existingReview->comment,
                'locked_at'   => $existingReview->locked_at?->toISOString(),
                'auto_submitted_at' => $existingReview->auto_submitted_at?->toISOString(),
            ] : null,
        ]);
    }

    public function store(StoreReviewRequest $request, Paper $paper): RedirectResponse
    {
        // Default to a FINAL submit (lock) unless the client explicitly sends
        // submit_final=false (autosave / "Save draft").
        $isFinal = ! $request->has('submit_final') || $request->boolean('submit_final');

        $paper->loadMissing('team', 'defenseAttempt.activeReviewerAssignments', 'subject');

        // Can't review a document that isn't turned in (e.g. the student unsubmitted it).
        abort_unless($paper->isTurnedIn(), 403, 'This document has not been turned in yet.');

        // Resolve which scoring responsibility (assigned role) this submission is for.
        // A judge holding multiple roles submits each one separately.
        $assignment = $this->resolveScoringResponsibility($request, $paper);

        // A locked review can't be re-saved (draft or final) until it's unlocked —
        // scoped to *this* responsibility so a judge's other role stays independent.
        $existing = $paper->reviews()
            ->where('reviewer_id', $request->user()->id)
            ->when(
                $assignment !== null,
                fn ($query) => $query->where('defense_attempt_reviewer_id', $assignment->id),
                fn ($query) => $query->whereNull('defense_attempt_reviewer_id'),
            )
            ->first();
        if ($existing && $existing->locked_at !== null) {
            abort(403, 'This review is locked. Ask the instructor to unlock it for editing.');
        }

        // Enforce the score submission deadline for the FINAL submit only — judges
        // may keep saving drafts right up to (and the instructor can still extend) it.
        $scoreDeadline = $paper->defenseAttempt?->score_deadline_at ?? $paper->team?->score_deadline_at;
        if ($isFinal && $scoreDeadline && $scoreDeadline->isPast()) {
            return back()->withErrors([
                'deadline' => 'The scoring deadline has passed. Contact the subject teacher to extend it.',
            ]);
        }

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
            [
                'reviewer_id' => $request->user()->id,
                'defense_attempt_reviewer_id' => $assignment?->id,
            ],
            [
                'defense_attempt_id' => $paper->defense_attempt_id,
                'committee_role' => $committeeRole,
                'scores_json' => $scoresJson,
                'comment' => $comment,
                'is_submitted' => $isFinal,
                'locked_at' => $isFinal ? now() : null,
            ],
        );

        // Draft save — quiet, no recalculation, no notification. Just persist + ack.
        if (! $isFinal) {
            return back(303)->with('draft_saved_at', now()->toIso8601String());
        }

        $this->reviewScoringService->recalculateFinalScore($paper);

        $review->load('paper.subject.teacher', 'reviewer');
        if ($paper->subject->teacher) {
            // Queue (not send) so a mail-render or SMTP hiccup can never 500 the
            // submit request — the review is already saved; delivery is best-effort.
            Mail::to($paper->subject->teacher)->queue(new ReviewCompletedMail($review));
            \App\Support\Notify::send(
                $paper->subject->teacher,
                'Review submitted',
                $review->reviewer->name.' submitted a review for '.$paper->team->name.'.',
                route('papers.show', $paper),
                'review',
            );
        }

        return to_route('papers.show', $paper)
            ->with('success', 'Review submitted successfully.');
    }

    public function show(Request $request, Review $review): Response
    {
        $user = $request->user();
        $review->load(['paper.team.members', 'paper.subject', 'paper.defenseAttempt.activeReviewerAssignments', 'reviewer']);

        $isTeamMember = $review->paper->team && $review->paper->team->members->contains('id', $user->id);

        $canAccess = $user->isAdmin()
            || $review->reviewer_id === $user->id
            || $review->paper->subject->teacher_id === $user->id
            // Students can only see reviews after the paper is published.
            || ($isTeamMember && $review->paper->visibility_status === 'published');
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

    /**
     * Work out which scoring responsibility (assigned role) a submission targets.
     * Returns null for the subject owner/admin reviewing without a formal
     * assignment, or legacy papers with no defense attempt.
     */
    private function resolveScoringResponsibility(Request $request, Paper $paper): ?\App\Models\DefenseAttemptReviewer
    {
        $assignments = ($paper->defenseAttempt?->activeReviewerAssignments ?? collect())
            ->where('reviewer_id', $request->user()->id)
            ->values();

        $requestedId = $request->integer('defense_attempt_reviewer_id') ?: null;

        if ($requestedId !== null) {
            $assignment = $assignments->firstWhere('id', $requestedId);
            abort_if($assignment === null, 403, 'That scoring role is not assigned to you for this defense session.');

            return $assignment;
        }

        if ($assignments->count() > 1) {
            abort(422, 'Select which scoring role you are submitting for this defense session.');
        }

        return $assignments->first();
    }

    private function reviewerCanAccessPaper(Paper $paper, \App\Models\User $user): bool
    {
        if ($paper->defenseAttempt) {
            return $paper->defenseAttempt->activeReviewerAssignments->contains('reviewer_id', $user->id);
        }

        return $paper->team && $paper->team->members->contains('id', $user->id);
    }
}
