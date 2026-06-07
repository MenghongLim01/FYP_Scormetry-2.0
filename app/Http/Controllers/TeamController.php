<?php

namespace App\Http\Controllers;

use App\Jobs\RemoveDefenseCalendarEvent;
use App\Jobs\SyncDefenseCalendarEvent;
use App\Mail\DefenseScheduledMail;
use App\Mail\ResultReleasedMail;
use App\Mail\TeamAdvisorInviteMail;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\GoogleCalendarEvent;
use App\Models\Review;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $teams = Team::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('subject.students', fn ($query) => $query->where('users.id', $user->id))
            ->with([
                'subject:id,title,teacher_id',
                'subject.teacher:id,name,email',
                'subject.reviewers:id,name,email',
                'advisor:id,name,email',
                'members:id,name,email',
                'papers:id,team_id,final_score,final_score_override,visibility_status',
            ])
            ->get(['teams.id', 'teams.subject_id', 'teams.advisor_id', 'teams.name', 'teams.topic', 'teams.defense_date', 'teams.defense_time', 'teams.defense_duration', 'teams.defense_room', 'teams.results_released_at']);

        return Inertia::render('teams/Index', [
            'teams' => $this->decorateTeamMemberRoles($teams),
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
            'topic' => ['nullable', 'string', 'max:255'],
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

        // When a student creates their own team, drop them into it automatically.
        if (! $user->isAdmin() && ! $isTeacher && $isEnrolled) {
            $team->members()->syncWithoutDetaching([$user->id]);
        }

        return back()->with('success', 'Team created successfully.');
    }

    public function updateTopic(Request $request, Team $team): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject');

        $isOwner = $team->subject->teacher_id === $authUser->id;
        $isStudentMember = $team->members()->where('users.id', $authUser->id)->exists()
            && $team->subject->students()->where('users.id', $authUser->id)->exists();

        abort_unless($authUser->isAdmin() || $isOwner || $isStudentMember, 403);

        $validated = $request->validate([
            'topic' => ['nullable', 'string', 'max:255'],
        ]);

        $team->update([
            'topic' => filled($validated['topic'] ?? null)
                ? trim((string) $validated['topic'])
                : null,
        ]);

        return back()->with('success', 'Team topic updated.');
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

        if ($isReviewer) {
            return back()->withErrors([
                'email' => 'This user is a reviewer. Assign judges from Evaluation Rounds using Manage Judges for the correct defense session.',
            ]);
        }

        $alreadyInAnotherTeam = $subject->teams()
            ->whereHas('members', fn ($q) => $q->where('users.id', $targetUser->id))
            ->where('id', '!=', $team->id)
            ->exists();

        if ($alreadyInAnotherTeam) {
            return back()->withErrors(['email' => 'This student is already a member of another team in this subject.']);
        }

        $isEnrolled = $subject->students()->where('users.id', $targetUser->id)->exists();

        // A student inviting someone who is NOT enrolled in the class yet creates a
        // pending request — the subject owner must approve before they join.
        if (! $authUser->isAdmin() && ! $isOwner && ! $isEnrolled) {
            return $this->createPendingTeamRequest($team, $subject, $targetUser, 'member', $authUser);
        }

        // Use updateOrCreate rather than students()->syncWithoutDetaching: the
        // relation filters on status = 'approved', so sync would not see an
        // existing pending/blocked membership row and would attempt a duplicate
        // insert, violating the (subject_id, user_id) unique key.
        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $targetUser->id],
            ['role' => 'student', 'status' => 'approved', 'role_label' => null],
        );

        $team->members()->syncWithoutDetaching([$targetUser->id]);

        return back();
    }

    /**
     * Record a pending team request (a teammate or advisor that a student invited
     * who isn't in the subject yet) and notify the subject owner for approval.
     */
    private function createPendingTeamRequest(Team $team, Subject $subject, User $targetUser, string $role, User $invitedBy): RedirectResponse
    {
        $existing = TeamRequest::where('team_id', $team->id)
            ->where('user_id', $targetUser->id)
            ->where('role', $role)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return back()->with('success', 'A pending request already exists for this person.');
        }

        TeamRequest::create([
            'team_id' => $team->id,
            'subject_id' => $subject->id,
            'email' => $targetUser->email,
            'user_id' => $targetUser->id,
            'role' => $role,
            'invited_by' => $invitedBy->id,
            'status' => 'pending',
        ]);

        $subject->loadMissing('teacher');
        if ($subject->teacher) {
            Notify::send(
                $subject->teacher,
                $role === 'advisor' ? 'Advisor approval needed' : 'New member approval needed',
                $invitedBy->name.' invited '.$targetUser->name.' to '.$team->name.' ('.$subject->title.'). Approve to add them.',
                route('subjects.show', $subject),
                'team',
            );
        }

        if ($role === 'advisor') {
            Mail::to($targetUser)->send(new TeamAdvisorInviteMail($targetUser, $team, $subject, $invitedBy->name, true));
        }

        return back()->with('success', 'Invitation sent — pending approval from the subject owner.');
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

            $hasSubmittedReview = Review::where('reviewer_id', $user->id)
                ->where('is_submitted', true)
                ->whereHas('paper', fn ($query) => $query->where('team_id', $team->id))
                ->exists();

            if ($hasSubmittedReview) {
                return back()->withErrors([
                    'reviewer' => 'This judge already submitted feedback for this team, so they cannot be removed from the team history.',
                ]);
            }

            // A reviewer may be assigned across several of the team's defense
            // attempts (e.g. midterm + final, or a re-defense round). Unassigning
            // them "from the team" must clear every attempt — otherwise a leftover
            // active assignment keeps them on the team and they never disappear.
            $assignments = DefenseAttemptReviewer::where('reviewer_id', $user->id)
                ->whereHas('attempt', fn ($query) => $query->where('team_id', $team->id))
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->delete();
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

    /**
     * Set (or change) the team's advisor by email. The advisor is just listed on
     * the team — it does NOT make them a judge. The FYP instructor still invites
     * judges separately in Evaluation Rounds.
     */
    public function setAdvisor(Request $request, Team $team): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject');

        $isOwner = $team->subject->teacher_id === $authUser->id;
        $isTeamMember = $team->members()->where('users.id', $authUser->id)->exists();
        abort_unless($authUser->isAdmin() || $isOwner || $isTeamMember, 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $advisor = User::where('email', $validated['email'])->firstOrFail();
        $subject = $team->subject;

        // An advisor supervises — they should not also be a student on the team.
        if ($team->members()->where('users.id', $advisor->id)->exists()
            && $subject->students()->where('users.id', $advisor->id)->exists()) {
            return back()->withErrors(['email' => 'This person is a student member of the team and cannot be set as the advisor.']);
        }

        // Is the advisor already part of the subject (owner / reviewer / member)?
        $advisorInSubject = $subject->teacher_id === $advisor->id
            || SubjectMember::where('subject_id', $subject->id)->where('user_id', $advisor->id)->exists();

        // The owner can set anyone directly. A student can set an advisor who is
        // already in the subject directly; otherwise it needs the owner's approval.
        if ($authUser->isAdmin() || $isOwner || $advisorInSubject) {
            $team->update(['advisor_id' => $advisor->id]);
            Mail::to($advisor)->send(new TeamAdvisorInviteMail($advisor, $team, $subject, $authUser->name, false));
            Notify::send(
                $advisor,
                'You are now a team advisor',
                'You were set as the advisor for '.$team->name.' in '.$subject->title.'.',
                route('subjects.show', $subject),
                'team',
            );

            return back()->with('success', $advisor->name.' set as the team advisor.');
        }

        // Student inviting an outside advisor — needs subject-owner approval.
        return $this->createPendingTeamRequest($team, $subject, $advisor, 'advisor', $authUser);
    }

    public function removeAdvisor(Request $request, Team $team): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject');

        // Only the subject owner (or admin) can actually remove an advisor.
        abort_unless($authUser->isAdmin() || $team->subject->teacher_id === $authUser->id, 403);

        $team->update(['advisor_id' => null]);

        return back()->with('success', 'Team advisor removed.');
    }

    /**
     * A student can't remove the advisor directly — they ask the subject owner.
     */
    public function requestAdvisorRemoval(Request $request, Team $team): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject.teacher', 'advisor');

        $isTeamMember = $team->members()->where('users.id', $authUser->id)->exists();
        abort_unless($isTeamMember, 403);
        abort_if($team->advisor_id === null, 422, 'This team has no advisor.');

        $this->createRemovalRequest($team, $team->subject, $team->advisor, 'remove_advisor', $authUser);

        return back()->with('success', 'Removal request sent — pending the subject owner.');
    }

    /**
     * A student can't remove a teammate directly — they ask the subject owner.
     */
    public function requestMemberRemoval(Request $request, Team $team, User $user): RedirectResponse
    {
        $authUser = $request->user();
        $team->load('subject.teacher');

        $isTeamMember = $team->members()->where('users.id', $authUser->id)->exists();
        abort_unless($isTeamMember, 403);
        abort_unless($team->members()->where('users.id', $user->id)->exists(), 422, 'That person is not on this team.');

        $this->createRemovalRequest($team, $team->subject, $user, 'remove_member', $authUser);

        return back()->with('success', 'Removal request sent — pending the subject owner.');
    }

    /**
     * Record a pending removal request (advisor or member) and notify the owner.
     */
    private function createRemovalRequest(Team $team, Subject $subject, User $target, string $role, User $requestedBy): void
    {
        $exists = TeamRequest::where('team_id', $team->id)
            ->where('user_id', $target->id)
            ->where('role', $role)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return;
        }

        TeamRequest::create([
            'team_id' => $team->id,
            'subject_id' => $subject->id,
            'email' => $target->email,
            'user_id' => $target->id,
            'role' => $role,
            'invited_by' => $requestedBy->id,
            'status' => 'pending',
        ]);

        $subject->loadMissing('teacher');
        if ($subject->teacher) {
            Notify::send(
                $subject->teacher,
                'Removal request',
                $requestedBy->name.' requested to remove '.$target->name.' from '.$team->name.' ('.$subject->title.').',
                route('subjects.show', $subject),
                'team',
            );
        }
    }

    /**
     * Subject owner approves a pending team request (add member, add advisor, or a removal).
     */
    public function approveTeamRequest(Request $request, TeamRequest $teamRequest): RedirectResponse
    {
        $authUser = $request->user();
        $teamRequest->load('team', 'subject', 'user');
        abort_unless($authUser->isAdmin() || $teamRequest->subject->teacher_id === $authUser->id, 403);
        abort_unless($teamRequest->status === 'pending', 422, 'This request has already been handled.');
        abort_if($teamRequest->user === null, 422, 'The user no longer exists.');

        switch ($teamRequest->role) {
            case 'remove_advisor':
                if ($teamRequest->team->advisor_id === $teamRequest->user_id) {
                    $teamRequest->team->update(['advisor_id' => null]);
                }
                break;
            case 'remove_member':
                $teamRequest->team->members()->detach($teamRequest->user_id);
                break;
            case 'advisor':
                $teamRequest->team->update(['advisor_id' => $teamRequest->user_id]);
                break;
            default: // 'member'
                SubjectMember::updateOrCreate(
                    ['subject_id' => $teamRequest->subject_id, 'user_id' => $teamRequest->user_id],
                    ['role' => 'student', 'status' => 'approved', 'role_label' => null],
                );
                $teamRequest->team->members()->syncWithoutDetaching([$teamRequest->user_id]);
        }

        $teamRequest->update(['status' => 'approved']);

        // Only notify the user when they were added (not when they're removed).
        if (in_array($teamRequest->role, ['member', 'advisor'], true)) {
            Notify::send(
                $teamRequest->user,
                $teamRequest->role === 'advisor' ? 'You are now a team advisor' : 'You joined a team',
                'Your request for '.$teamRequest->team->name.' in '.$teamRequest->subject->title.' was approved.',
                route('subjects.show', $teamRequest->subject),
                'team',
            );
        }

        return back()->with('success', 'Request approved.');
    }

    public function rejectTeamRequest(Request $request, TeamRequest $teamRequest): RedirectResponse
    {
        $authUser = $request->user();
        $teamRequest->load('subject');
        abort_unless($authUser->isAdmin() || $teamRequest->subject->teacher_id === $authUser->id, 403);
        abort_unless($teamRequest->status === 'pending', 422, 'This request has already been handled.');

        $teamRequest->update(['status' => 'rejected']);

        return back()->with('success', 'Request rejected.');
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

        $effectiveScore = $paper?->final_score_override ?? $paper?->final_score;

        // Show pending page for students when results are not released, no paper exists, or no score exists yet.
        if ($isStudent && ($attempt->results_released_at === null || $paper === null || $effectiveScore === null)) {
            return Inertia::render('teams/ResultPending', [
                'team' => $team->only(['id', 'name']),
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
                'criteria' => $c['criteria'],
                'weight' => $weight,
                'max_score' => $maxScore,
                'avg_score' => $avg,
                'weighted' => $weighted,
                'judge_scores' => $submittedReviews->map(fn ($r) => [
                    'judge' => $r->reviewer->name,
                    'score' => collect($r->scores_json ?? [])->firstWhere('criteria', $c['criteria'])['score'] ?? null,
                    'comment' => collect($r->scores_json ?? [])->firstWhere('criteria', $c['criteria'])['comment'] ?? null,
                ])->values(),
            ];
        })->values();

        return Inertia::render('teams/StudentResult', [
            'team' => [
                ...$team->only(['id', 'name']),
                'results_released_at' => $attempt->results_released_at,
            ],
            'subject' => [
                ...$subject->only(['id', 'title']),
                'passing_score' => $period?->passing_score ?? $subject->passing_score,
            ],
            'paper' => $paper->only([
                'id',
                'final_score',
                'final_score_override',
                'final_score_override_reason',
                'visibility_status',
            ]),
            'criteriaBreakdown' => $criteriaBreakdown,
            'judgeComments' => $submittedReviews->filter(fn ($r) => $r->comment)->map(fn ($r) => [
                'judge' => $r->reviewer->name,
                'comment' => $r->comment,
            ])->values(),
        ]);
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, Team>
     */
    private function decorateTeamMemberRoles(Collection $teams): Collection
    {
        return $teams->map(function (Team $team): Team {
            $subject = $team->subject;
            $reviewers = $subject?->reviewers ?? collect();
            $reviewerRoleById = $reviewers->mapWithKeys(fn (User $reviewer): array => [
                $reviewer->id => $reviewer->pivot?->role_label ?: $reviewer->pivot?->role ?: 'Judge / Reviewer',
            ]);

            $panelIds = $reviewerRoleById
                ->keys()
                ->push($subject?->teacher_id)
                ->filter()
                ->unique()
                ->values();

            $studentMembers = $team->members
                ->reject(fn (User $member): bool => $panelIds->contains($member->id))
                ->values()
                ->map(fn (User $member): array => $member->only(['id', 'name', 'email']));

            $team->setAttribute('student_members', $studentMembers);

            return $team;
        });
    }

    public function updateSchedule(Request $request, Team $team): RedirectResponse
    {
        $team->load('subject');
        $user = $request->user();
        abort_unless($user->isAdmin() || $team->subject->teacher_id === $user->id, 403);

        $validated = $request->validate([
            'defense_date' => ['nullable', 'date'],
            'defense_time' => ['nullable', 'date_format:H:i'],
            'defense_duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'defense_room' => ['nullable', 'string', 'max:255'],
            'paper_upload_deadline_at' => ['nullable', 'date'],
            'score_deadline_at' => ['nullable', 'date'],
        ]);
        $validated = $this->withDefaultDeadlines($validated);

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

            if ($changeType === 'cancelled') {
                $this->removeCalendarForAttempt($attempt);
            } else {
                $this->syncCalendarForActiveReviewers($attempt);
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

        $isOwner = $subject->teacher_id === $user->id;
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
            'team' => [
                ...$team->only(['id', 'name']),
                'defense_date' => $attempt->defense_date?->format('Y-m-d'),
                'defense_time' => $attempt->defense_time,
                'defense_duration' => $attempt->defense_duration,
                'defense_room' => $attempt->defense_room,
                'score_deadline_at' => $attempt->score_deadline_at?->toISOString(),
                'results_released_at' => $attempt->results_released_at?->toISOString(),
            ],
            'subject' => [
                ...$subject->only(['id', 'title']),
                'passing_score' => $attempt->period?->passing_score ?? $subject->passing_score,
            ],
            'paper' => $paper ? $paper->only(['id', 'final_score', 'final_score_override', 'final_score_override_reason', 'visibility_status']) : null,
            'criteria' => $criteria,
            'assignedJudges' => $assignedJudges->map(fn ($j) => ['id' => $j->id, 'name' => $j->name]),
            'reviews' => $reviews->map(fn ($r) => [
                'id' => $r->id,
                'reviewer' => ['id' => $r->reviewer_id, 'name' => $r->reviewer->name],
                'scores_json' => $r->scores_json,
                'comment' => $r->comment,
                'is_submitted' => $r->is_submitted,
                'locked_at' => $r->locked_at,
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

        // Integrity guard: results can't be released before a score is calculated.
        if ($paper->effectiveFinalScore() === null) {
            return back()->withErrors([
                'release' => 'This team has no calculated score yet. At least one judge must submit a review before results can be released.',
            ]);
        }

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

        Notify::many(
            $students,
            'Your results are available',
            'Results for '.$team->name.' ('.$subject->title.') have been released.',
            route('teams.result', $team),
            'result',
        );

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
            $attempt = $team->defenseAttempts()->firstOrCreate(
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

            $attempt->ensureOwnerIsReviewer();
        }
    }

    /**
     * @return array{defense_date: string|null, defense_time: string|null, defense_duration: int|null, defense_room: string|null, paper_upload_deadline_at: string|null, score_deadline_at: string|null}
     */
    private function scheduleSnapshot(DefenseAttempt $defenseAttempt): array
    {
        return [
            'defense_date' => $defenseAttempt->defense_date?->format('Y-m-d'),
            'defense_time' => $defenseAttempt->defense_time ? substr($defenseAttempt->defense_time, 0, 5) : null,
            'defense_duration' => $defenseAttempt->defense_duration,
            'defense_room' => $defenseAttempt->defense_room,
            'paper_upload_deadline_at' => $defenseAttempt->paper_upload_deadline_at?->toISOString(),
            'score_deadline_at' => $defenseAttempt->score_deadline_at?->toISOString(),
        ];
    }

    /**
     * @param  array{defense_date?: string|null, defense_time?: string|null, defense_duration?: int|null, defense_room?: string|null, paper_upload_deadline_at?: mixed, score_deadline_at?: mixed}  $validated
     * @return array{defense_date?: string|null, defense_time?: string|null, defense_duration?: int|null, defense_room?: string|null, paper_upload_deadline_at?: mixed, score_deadline_at?: mixed}
     */
    private function withDefaultDeadlines(array $validated): array
    {
        $startsAt = $this->scheduleStartsAt($validated);

        if (! $startsAt) {
            return $validated;
        }

        if (empty($validated['paper_upload_deadline_at'])) {
            $validated['paper_upload_deadline_at'] = $startsAt->copy()->subDay()->setTime(12, 0);
        }

        if (empty($validated['score_deadline_at'])) {
            $validated['score_deadline_at'] = $startsAt->copy()->addDay()->setTime(12, 0);
        }

        return $validated;
    }

    /**
     * @param  array{defense_date?: string|null, defense_time?: string|null}  $schedule
     */
    private function scheduleStartsAt(array $schedule): ?Carbon
    {
        if (empty($schedule['defense_date'])) {
            return null;
        }

        $time = $schedule['defense_time'] ?? '23:59';

        return Carbon::parse($schedule['defense_date'].' '.substr($time, 0, 5), config('app.timezone'));
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

    private function scheduleRecipients(DefenseAttempt $defenseAttempt): Collection
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

    private function syncCalendarForActiveReviewers(DefenseAttempt $defenseAttempt): void
    {
        $defenseAttempt->loadMissing('activeReviewerAssignments.reviewer');

        foreach ($defenseAttempt->activeReviewerAssignments as $assignment) {
            SyncDefenseCalendarEvent::dispatch(
                $defenseAttempt->id,
                $assignment->reviewer_id,
                $assignment->committee_role,
            );
        }
    }

    private function removeCalendarForAttempt(DefenseAttempt $defenseAttempt): void
    {
        $events = GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)
            ->whereNotNull('google_event_id')
            ->get();

        foreach ($events as $event) {
            RemoveDefenseCalendarEvent::dispatch($event->user_id, $event->google_event_id);
        }

        GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)->delete();
    }
}
