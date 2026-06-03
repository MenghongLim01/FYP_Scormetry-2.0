<?php

namespace App\Http\Controllers;

use App\Mail\DefenseScheduledMail;
use App\Mail\ResultReleasedMail;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Review;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = $request->user()
            ->teams()
            ->with([
                'subject:id,title',
                'members:id,name,email',
                'papers:id,team_id,final_score,visibility_status',
            ])
            ->get(['teams.id', 'teams.subject_id', 'teams.name', 'teams.defense_date', 'teams.defense_time', 'teams.defense_duration', 'teams.defense_room', 'teams.results_released_at']);

        return Inertia::render('teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request, Subject $subject): RedirectResponse
    {
        $user = $request->user();
        $isTeacher = $subject->teacher_id === $user->id;
        $isEnrolled = $subject->students()->where('users.id', $user->id)->exists();
        abort_unless($user->isAdmin() || $isTeacher || $isEnrolled, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Reject duplicate team names within the same subject (case-insensitive)
        $nameExists = $subject->teams()
            ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
            ->exists();

        if ($nameExists) {
            return back()->withErrors(['name' => 'A team with this name already exists in this subject.']);
        }

        $team = $subject->teams()->create($validated);
        $this->ensureDefaultAttemptsForTeam($team);

        return back()->with('success', 'Team created successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $user = request()->user();
        $team->load('subject');
        abort_unless($user->isAdmin() || $team->subject->teacher_id === $user->id, 403);

        $team->delete();

        return back()->with('success', 'Team deleted.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject');
        $isOwner = $team->subject->teacher_id === $authUser->id;
        $isMember = $team->members()->where('users.id', $authUser->id)->exists();
        abort_unless($authUser->isAdmin() || $isOwner || $isMember, 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $targetUser = User::where('email', $validated['email'])->firstOrFail();

        $subject = $team->subject;
        $isReviewer = $subject->reviewers()->where('users.id', $targetUser->id)->exists();

        // Only the subject owner (or admin) may assign reviewers to a team.
        if ($isReviewer && ! ($authUser->isAdmin() || $isOwner)) {
            abort(403, 'Only the subject owner can assign reviewers to teams.');
        }

        if ($isReviewer) {
            $attempt = $this->ensureAttemptForTeam($team);
            $membership = $subject->memberships()
                ->where('user_id', $targetUser->id)
                ->where('role', '!=', 'student')
                ->first();

            $attempt->reviewerAssignments()->updateOrCreate(
                ['reviewer_id' => $targetUser->id],
                [
                    'committee_role' => $membership?->role_label ?: $membership?->role,
                    'status' => 'active',
                    'excluded_from_calculation' => false,
                    'removed_at' => null,
                    'removed_by' => null,
                ],
            );

            $team->members()->syncWithoutDetaching([$targetUser->id]);

            return back()->with('success', $targetUser->name.' assigned as reviewer.');
        }

        if (! $isReviewer) {
            $alreadyInAnotherTeam = $subject->teams()
                ->whereHas('members', fn ($q) => $q->where('users.id', $targetUser->id))
                ->where('id', '!=', $team->id)
                ->exists();

            if ($alreadyInAnotherTeam) {
                return back()->withErrors(['email' => 'This student is already a member of another team in this subject.']);
            }

            $subject->students()->syncWithoutDetaching([$targetUser->id]);
        }

        $team->members()->syncWithoutDetaching([$targetUser->id]);

        return back();
    }

    public function removeMember(Team $team, User $user): RedirectResponse
    {
        $authUser = request()->user();
        $team->load('subject');
        $isOwner = $team->subject->teacher_id === $authUser->id;
        $isReviewer = $team->subject->reviewers()->where('users.id', $user->id)->exists();

        // Reviewers can only be unassigned by the subject owner (or admin).
        if ($isReviewer) {
            abort_unless($authUser->isAdmin() || $isOwner, 403);

            $attempt = $this->ensureAttemptForTeam($team);
            $assignment = $attempt->reviewerAssignments()
                ->where('reviewer_id', $user->id)
                ->first();

            if ($assignment) {
                $hasSubmittedReview = Review::where('defense_attempt_id', $attempt->id)
                    ->where('reviewer_id', $user->id)
                    ->where('is_submitted', true)
                    ->exists();

                if ($hasSubmittedReview) {
                    $assignment->update([
                        'status' => 'removed',
                        'excluded_from_calculation' => true,
                        'removed_at' => now(),
                        'removed_by' => $authUser->id,
                    ]);
                } else {
                    $assignment->delete();
                }
            }

            $hasOtherAssignments = DefenseAttemptReviewer::where('reviewer_id', $user->id)
                ->where('status', 'active')
                ->whereHas('attempt', fn ($query) => $query->where('team_id', $team->id))
                ->exists();

            if (! $hasOtherAssignments) {
                $team->members()->detach($user->id);
            }

            return back()->with('success', 'Reviewer unassigned from this team.');
        } else {
            abort_unless(
                $authUser->isAdmin()
                || $isOwner
                || $authUser->id === $user->id,
                403,
            );
        }

        $team->members()->detach($user->id);

        return back()->with('success', 'Member removed from the team.');
    }

    public function leave(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $team->load('subject');

        // Reviewers cannot self-leave a team assignment; only the subject owner can unassign them.
        $isReviewer = $team->subject->reviewers()->where('users.id', $user->id)->exists();
        abort_if($isReviewer, 403, 'Only the subject owner can unassign reviewers from teams.');

        $team->members()->detach($user->id);

        return back()->with('success', 'You have left the team.');
    }

    public function result(Request $request, Team $team): Response
    {
        $user = $request->user();
        $team->load(['subject.rubric', 'members', 'defenseAttempts.period.rubric', 'papers.reviews.reviewer']);
        $subject = $team->subject;

        $isMember = $team->members->contains('id', $user->id);
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id || $isMember, 403);

        // Students cannot see unreleased results
        $isStudent = $isMember
            && ! $subject->reviewers()->where('users.id', $user->id)->exists()
            && $subject->teacher_id !== $user->id
            && ! $user->isAdmin();

        $attempt = $team->defenseAttempts()
            ->whereNotNull('results_released_at')
            ->with('period.rubric')
            ->latest('results_released_at')
            ->first() ?? $this->ensureAttemptForTeam($team);

        $paper = $attempt->papers()->latest()->with('reviews.reviewer')->first()
            ?? $team->papers()->latest()->with('reviews.reviewer')->first();

        // Show pending page for students when results are not released OR no paper yet
        if ($isStudent && ($attempt->results_released_at === null || $paper === null)) {
            return Inertia::render('teams/ResultPending', [
                'team'    => $team->only(['id', 'name']),
                'subject' => $subject->only(['id', 'title']),
            ]);
        }

        abort_if($paper === null, 404, 'No paper found for this team.');

        $period = $attempt->period;
        $rubric = $period?->rubric ?? $subject->rubric;
        $criteria = $rubric?->structure_json ?? [];
        $submittedReviews = $paper->reviews->where('is_submitted', true)->values();

        // Per-criterion averages and weighted contribution
        $criteriaBreakdown = collect($criteria)->map(function ($c) use ($submittedReviews) {
            $scores = $submittedReviews
                ->map(fn ($r) => collect($r->scores_json ?? [])->firstWhere('criteria', $c['criteria']))
                ->filter()
                ->pluck('score')
                ->values();

            $avg = $scores->count() > 0 ? round($scores->avg(), 2) : null;
            $maxScore = (int) ($c['max_score'] ?? 4);
            $weight = (float) ($c['weight'] ?? 0);
            $weighted = $avg !== null && $maxScore > 0
                ? round(($avg / $maxScore) * $weight, 2)
                : null;

            return [
                'criteria'   => $c['criteria'],
                'weight'     => $weight,
                'max_score'  => $maxScore,
                'avg_score'  => $avg,
                'weighted'   => $weighted,
                'judge_scores' => $submittedReviews->map(fn ($r) => [
                    'judge'   => $r->reviewer->name,
                    'score'   => collect($r->scores_json ?? [])->firstWhere('criteria', $c['criteria'])['score'] ?? null,
                    'comment' => collect($r->scores_json ?? [])->firstWhere('criteria', $c['criteria'])['comment'] ?? null,
                ])->values(),
            ];
        })->values();

        return Inertia::render('teams/StudentResult', [
            'team'             => [
                ...$team->only(['id', 'name']),
                'results_released_at' => $attempt->results_released_at,
            ],
            'subject'          => [
                ...$subject->only(['id', 'title']),
                'passing_score' => $period?->passing_score ?? $subject->passing_score,
            ],
            'paper'            => $paper->only([
                'id',
                'final_score',
                'final_score_override',
                'final_score_override_reason',
                'visibility_status',
            ]),
            'criteriaBreakdown' => $criteriaBreakdown,
            'judgeComments'    => $submittedReviews->filter(fn ($r) => $r->comment)->map(fn ($r) => [
                'judge'   => $r->reviewer->name,
                'comment' => $r->comment,
            ])->values(),
        ]);
    }

    public function updateSchedule(Request $request, Team $team): RedirectResponse
    {
        $team->load('subject');
        $user = $request->user();
        abort_unless($user->isAdmin() || $team->subject->teacher_id === $user->id, 403);

        $validated = $request->validate([
            'defense_date'      => ['nullable', 'date'],
            'defense_time'      => ['nullable', 'date_format:H:i'],
            'defense_duration'  => ['nullable', 'integer', 'min:5', 'max:480'],
            'defense_room'      => ['nullable', 'string', 'max:255'],
            'paper_upload_deadline_at' => ['nullable', 'date'],
            'score_deadline_at' => ['nullable', 'date'],
        ]);

        $attempt = $this->ensureAttemptForTeam($team);

        $attempt->load('period.subject.reviewers', 'team.members', 'activeReviewerAssignments.reviewer');
        $previousSchedule = $this->scheduleSnapshot($attempt);

        $attempt->update([
            ...$validated,
            'status' => ($validated['defense_date'] ?? null) ? 'scheduled' : 'setup',
        ]);

        $team->update([
            'defense_date' => $attempt->defense_date,
            'defense_time' => $attempt->defense_time,
            'defense_duration' => $attempt->defense_duration,
            'defense_room' => $attempt->defense_room,
            'score_deadline_at' => $attempt->score_deadline_at,
        ]);

        $attempt = $attempt->fresh()
            ->load('period.subject.reviewers', 'team.members', 'activeReviewerAssignments.reviewer');
        $currentSchedule = $this->scheduleSnapshot($attempt);
        $scheduleChanged = $this->scheduleChanged($previousSchedule, $currentSchedule);

        $message = 'Defense schedule updated.';

        if ($scheduleChanged && ($previousSchedule['defense_date'] !== null || $currentSchedule['defense_date'] !== null)) {
            $changeType = $this->scheduleChangeType($previousSchedule, $currentSchedule);
            $calendarSchedule = $changeType === 'cancelled' ? $previousSchedule : null;

            foreach ($this->scheduleRecipients($attempt) as $recipient) {
                Mail::to($recipient->email)->queue(new DefenseScheduledMail($attempt, $changeType, $calendarSchedule));
            }

            $message = match ($changeType) {
                'cancelled' => 'Defense schedule cancelled and calendar updates sent.',
                'updated' => 'Defense schedule updated and calendar invites sent.',
                default => 'Defense schedule set and calendar invites sent.',
            };
        }

        return back()->with('success', $message);
    }

    public function scores(Request $request, Team $team): Response
    {
        $user = $request->user();
        $team->load([
            'subject.rubric',
            'members',
            'defenseAttempts.period.rubric',
            'defenseAttempts.activeReviewerAssignments.reviewer',
            'defenseAttempts.papers.reviews.reviewer',
            'papers.reviews.reviewer',
        ]);
        $subject = $team->subject;
        $attempt = $this->ensureAttemptForTeam($team);

        $isOwner   = $subject->teacher_id === $user->id;
        $isReviewer = $subject->reviewers()->where('users.id', $user->id)->exists();
        $isAssigned = $attempt->activeReviewerAssignments->contains('reviewer_id', $user->id)
            || ($attempt->activeReviewerAssignments->isEmpty() && $team->members->contains('id', $user->id));
        abort_unless($user->isAdmin() || $isOwner || ($isReviewer && $isAssigned), 403);

        $paper = $attempt->papers()->latest()->with('reviews.reviewer')->first()
            ?? $team->papers()->latest()->with('reviews.reviewer')->first();
        $criteria = ($attempt->period?->rubric ?? $subject->rubric)?->structure_json ?? [];

        $reviewerIds = $subject->reviewers()->pluck('users.id');
        $assignedJudges = $attempt->activeReviewerAssignments->isNotEmpty()
            ? $attempt->activeReviewerAssignments->pluck('reviewer')->filter()->values()
            : $team->members->whereIn('id', $reviewerIds)->values();

        $reviews = $paper?->reviews ?? collect();

        return Inertia::render('teams/Scores', [
            'team'          => [
                ...$team->only(['id', 'name']),
                'defense_date' => $attempt->defense_date?->format('Y-m-d'),
                'defense_time' => $attempt->defense_time,
                'defense_duration' => $attempt->defense_duration,
                'defense_room' => $attempt->defense_room,
                'score_deadline_at' => $attempt->score_deadline_at?->toISOString(),
                'results_released_at' => $attempt->results_released_at?->toISOString(),
            ],
            'subject'       => [
                ...$subject->only(['id', 'title']),
                'passing_score' => $attempt->period?->passing_score ?? $subject->passing_score,
            ],
            'paper'         => $paper ? $paper->only(['id', 'final_score', 'final_score_override', 'final_score_override_reason', 'visibility_status']) : null,
            'criteria'      => $criteria,
            'assignedJudges' => $assignedJudges->map(fn ($j) => ['id' => $j->id, 'name' => $j->name]),
            'reviews'       => $reviews->map(fn ($r) => [
                'id'          => $r->id,
                'reviewer'    => ['id' => $r->reviewer_id, 'name' => $r->reviewer->name],
                'scores_json' => $r->scores_json,
                'comment'     => $r->comment,
                'is_submitted' => $r->is_submitted,
                'locked_at'   => $r->locked_at,
            ]),
            'isOwnerOrAdmin' => $isOwner || $user->isAdmin(),
        ]);
    }

    public function releaseScores(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $team->load(['subject.reviewers', 'members', 'papers.reviews', 'defenseAttempts.period']);
        $subject = $team->subject;
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);

        $attempt = $this->ensureAttemptForTeam($team);
        $paper = $attempt->papers()->latest()->first()
            ?? $team->papers()->latest()->first();
        abort_if($paper === null, 422, 'This team has no paper to release.');

        $paper->update(['visibility_status' => 'published']);
        $attempt->update([
            'results_released_at' => now(),
            'status' => 'published',
        ]);
        $team->update(['results_released_at' => $attempt->results_released_at]);

        $students = $team->members->filter(
            fn ($m) => ! $subject->reviewers->contains('id', $m->id),
        );

        foreach ($students as $student) {
            Mail::to($student)->send(new ResultReleasedMail($team));
        }

        return back()->with('success', 'Results released and students notified.');
    }

    private function ensureAttemptForTeam(Team $team, ?int $defenseAttemptId = null): DefenseAttempt
    {
        $team->loadMissing('subject');

        if ($defenseAttemptId !== null) {
            return $team->defenseAttempts()
                ->whereKey($defenseAttemptId)
                ->with(['period.rubric', 'activeReviewerAssignments.reviewer'])
                ->firstOrFail();
        }

        $attempt = $team->activeDefenseAttempt();

        if ($attempt) {
            return $attempt;
        }

        $period = DefensePeriod::firstOrCreate(
            ['subject_id' => $team->subject_id, 'type' => 'final'],
            [
                'name' => 'Final Defense',
                'sequence' => 2,
                'score_scale' => 'points_100',
                'passing_score' => $team->subject->passing_score ?: 50,
                'status' => 'setup',
            ],
        );

        return $team->defenseAttempts()->create([
            'defense_period_id' => $period->id,
            'label' => 'Attempt 1',
            'attempt_number' => 1,
            'attempt_type' => 'regular',
            'defense_date' => $team->defense_date,
            'defense_time' => $team->defense_time,
            'defense_duration' => $team->defense_duration,
            'defense_room' => $team->defense_room,
            'score_deadline_at' => $team->score_deadline_at,
            'results_released_at' => $team->results_released_at,
            'status' => $team->results_released_at ? 'published' : ($team->defense_date ? 'scheduled' : 'setup'),
        ])->load(['period.rubric', 'activeReviewerAssignments.reviewer']);
    }

    private function ensureDefaultAttemptsForTeam(Team $team): void
    {
        $team->loadMissing('subject');

        $periods = collect([
            ['name' => 'Midterm Defense', 'type' => 'midterm', 'sequence' => 1],
            ['name' => 'Final Defense', 'type' => 'final', 'sequence' => 2],
        ])->map(fn (array $period) => DefensePeriod::firstOrCreate(
            ['subject_id' => $team->subject_id, 'type' => $period['type']],
            [
                'name' => $period['name'],
                'sequence' => $period['sequence'],
                'score_scale' => 'points_100',
                'passing_score' => $team->subject->passing_score ?: 50,
                'status' => 'setup',
            ],
        ));

        foreach ($periods as $period) {
            $team->defenseAttempts()->firstOrCreate(
                [
                    'defense_period_id' => $period->id,
                    'attempt_number' => 1,
                ],
                [
                    'label' => 'Attempt 1',
                    'attempt_type' => 'regular',
                    'status' => 'setup',
                ],
            );
        }
    }

    /**
     * @return array{defense_date: string|null, defense_time: string|null, defense_duration: int|null, defense_room: string|null, score_deadline_at: string|null}
     */
    private function scheduleSnapshot(DefenseAttempt $defenseAttempt): array
    {
        return [
            'defense_date' => $defenseAttempt->defense_date?->format('Y-m-d'),
            'defense_time' => $defenseAttempt->defense_time ? substr($defenseAttempt->defense_time, 0, 5) : null,
            'defense_duration' => $defenseAttempt->defense_duration,
            'defense_room' => $defenseAttempt->defense_room,
            'score_deadline_at' => $defenseAttempt->score_deadline_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $previousSchedule
     * @param  array<string, mixed>  $currentSchedule
     */
    private function scheduleChanged(array $previousSchedule, array $currentSchedule): bool
    {
        return $previousSchedule !== $currentSchedule;
    }

    /**
     * @param  array<string, mixed>  $previousSchedule
     * @param  array<string, mixed>  $currentSchedule
     */
    private function scheduleChangeType(array $previousSchedule, array $currentSchedule): string
    {
        if ($previousSchedule['defense_date'] !== null && $currentSchedule['defense_date'] === null) {
            return 'cancelled';
        }

        return $previousSchedule['defense_date'] === null ? 'scheduled' : 'updated';
    }

    private function scheduleRecipients(DefenseAttempt $defenseAttempt): \Illuminate\Support\Collection
    {
        $reviewerIds = $defenseAttempt->period->subject->reviewers->pluck('id');
        $studentMembers = $defenseAttempt->team->members->reject(
            fn (User $member) => $reviewerIds->contains($member->id),
        );

        return $studentMembers
            ->merge($defenseAttempt->activeReviewerAssignments->pluck('reviewer'))
            ->filter()
            ->unique('id')
            ->values();
    }
}
