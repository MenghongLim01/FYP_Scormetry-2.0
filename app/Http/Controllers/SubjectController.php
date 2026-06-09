<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Mail\ReviewerAddedMail;
use App\Mail\ReviewerInvitationMail;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Subject;
use App\Models\SubjectBlockedEmail;
use App\Models\SubjectInvitation;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $showArchived = $request->boolean('archived');

        $base = match (true) {
            $user->isAdmin() => Subject::query()
                ->with('teacher')
                ->withCount(['students', 'papers', 'reviewers']),
            $user->isTeacher() => Subject::query()
                ->with('teacher')
                ->withCount(['students', 'papers', 'reviewers'])
                ->where(function ($query) use ($user) {
                    $query->where('teacher_id', $user->id)
                        ->orWhereHas('reviewers', fn ($q) => $q->where('users.id', $user->id));
                }),
            default => $user->enrolledSubjects()->with('teacher')->withCount(['students', 'papers']),
        };

        // Count of archived (for the toggle), then filter to the requested view.
        $archivedCount = (clone $base)->whereNotNull('subjects.archived_at')->count();

        $subjects = $base
            ->when($showArchived,
                fn ($q) => $q->whereNotNull('subjects.archived_at'),
                fn ($q) => $q->whereNull('subjects.archived_at'),
            )
            ->latest()
            ->get();

        // Per-user state: pins (float to top) + custom drag order, then recency.
        $pinnedIds = $user->pinnedSubjects()->pluck('subjects.id')->flip();
        $positions = $user->subjectOrders()->pluck('position', 'subject_id');

        $subjects = $subjects
            ->map(function (Subject $subject) use ($pinnedIds, $positions) {
                $subject->setAttribute('is_pinned', $pinnedIds->has($subject->id));
                $subject->setAttribute('is_archived', $subject->isArchived());
                $subject->setAttribute('sort_position', $positions[$subject->id] ?? null);

                return $subject;
            })
            ->sort(function (Subject $a, Subject $b) {
                // 1) pinned first
                $pin = (int) $b->getAttribute('is_pinned') <=> (int) $a->getAttribute('is_pinned');
                if ($pin !== 0) {
                    return $pin;
                }
                // 2) custom position (set positions before unset)
                $pa = $a->getAttribute('sort_position');
                $pb = $b->getAttribute('sort_position');
                if ($pa !== null && $pb !== null) {
                    return $pa <=> $pb;
                }
                if ($pa !== null) {
                    return -1;
                }
                if ($pb !== null) {
                    return 1;
                }

                // 3) newest first
                return $b->created_at <=> $a->created_at;
            })
            ->values();

        return Inertia::render('subjects/Index', [
            'subjects' => $subjects,
            'showingArchived' => $showArchived,
            'archivedCount' => $archivedCount,
        ]);
    }

    public function archive(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $subject->update(['archived_at' => now()]);

        return back()->with('success', '“'.$subject->title.'” archived.');
    }

    public function unarchive(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $subject->update(['archived_at' => null]);

        return back()->with('success', '“'.$subject->title.'” restored.');
    }

    /**
     * Save the current user's custom ordering of subjects (drag-and-drop).
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:subjects,id'],
        ]);

        $user = $request->user();
        foreach ($validated['order'] as $position => $subjectId) {
            $user->subjectOrders()->updateOrCreate(
                ['subject_id' => $subjectId],
                ['position' => $position],
            );
        }

        return back()->with('success', 'Your subject order has been saved.');
    }

    public function togglePin(Request $request, Subject $subject): RedirectResponse
    {
        $user = $request->user();

        // Only let a user pin a subject they can actually see.
        $canSee = $user->isAdmin()
            || $subject->teacher_id === $user->id
            || $subject->reviewers()->where('users.id', $user->id)->exists()
            || $subject->students()->where('users.id', $user->id)->exists();
        abort_unless($canSee, 403);

        $changed = $user->pinnedSubjects()->toggle($subject->id);
        $pinned = ! empty($changed['attached']);

        return back()->with('success', $pinned
            ? '“'.$subject->title.'” pinned to the top.'
            : '“'.$subject->title.'” unpinned.');
    }

    public function create(): Response
    {
        return Inertia::render('subjects/Create');
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = $request->user()->teachingSubjects()->create([
            ...$request->validated(),
            'join_code' => Str::upper(Str::random(6)),
            'reviewer_code' => Str::upper(Str::random(6)),
        ]);

        $this->createDefaultDefensePeriods($subject);

        return to_route('subjects.show', $subject)
            ->with('success', 'Subject created successfully. Share the classroom code with your students.');
    }

    /**
     * Approve every pending reviewer request across all of this subject's
     * defense attempts in one click.
     */
    public function approveAllReviewerRequests(Request $request, Subject $subject): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);

        $approvedMemberIds = SubjectMember::where('subject_id', $subject->id)
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->pluck('role_label', 'user_id');

        $teamIds = Team::where('subject_id', $subject->id)->pluck('id');
        $pending = DefenseAttemptReviewer::whereIn(
            'defense_attempt_id',
            DefenseAttempt::whereIn('team_id', $teamIds)->pluck('id'),
        )->where('status', 'pending')->with('attempt.team')->get();

        $count = 0;
        foreach ($pending as $assignment) {
            // Only approve requests from users who are genuinely approved reviewers.
            if (! $approvedMemberIds->has($assignment->reviewer_id)) {
                continue;
            }

            $assignment->update([
                'status' => 'active',
                'excluded_from_calculation' => false,
                'removed_at' => null,
                'removed_by' => null,
            ]);
            $assignment->attempt?->team?->members()->syncWithoutDetaching([$assignment->reviewer_id]);
            $count++;
        }

        return back()->with('success', $count === 0
            ? 'No pending reviewer requests to approve.'
            : $count.' reviewer '.($count === 1 ? 'request' : 'requests').' approved.');
    }

    public function show(Request $request, Subject $subject): Response
    {
        $user = $request->user();
        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->first();

        $isOwner = $subject->teacher_id === $user->id;

        if (! $user->isAdmin() && ! $isOwner) {
            abort_unless($membership !== null, 403);

            if ($membership->status === 'pending') {
                return Inertia::render('subjects/PendingApproval', [
                    'subject' => $subject->only(['id', 'title']),
                ]);
            }

            if ($membership->status === 'blocked') {
                return Inertia::render('subjects/Blocked', [
                    'subject' => $subject->only(['id', 'title']),
                ]);
            }
        }

        $this->ensureRoundAttempts($subject);

        $subject->load([
            'teacher',
            'students',
            'reviewers',
            'pendingInvitations',
            'pendingMembers.user',
            'rubric',
            'rubrics',
            'defensePeriods.rubric',
            'defensePeriods.attempts.team.members',
            'defensePeriods.attempts.team.advisor',
            'defensePeriods.attempts.papers.reviews.reviewer',
            'defensePeriods.attempts.reviewerAssignments.reviewer',
            'defensePeriods.attempts.activeReviewerAssignments.reviewer',
            'teams.members',
            'teams.advisor',
            'teams.requests' => fn ($q) => $q->where('status', 'pending')->with(['user', 'invitedBy']),
            'papers.team.members',
            'papers.reviews.reviewer',
        ]);

        $isStudent = $membership?->status === 'approved' && $membership?->role === 'student';
        $isReviewer = $membership?->status === 'approved' && $membership?->role !== 'student';

        // Students can only see their own team's papers
        if ($isStudent && ! $user->isAdmin() && ! $isOwner) {
            $studentTeamIds = $subject->teams->filter(
                fn ($team) => $team->members->contains('id', $user->id),
            )->pluck('id');

            $subject->setRelation(
                'papers',
                $subject->papers->filter(fn ($paper) => $studentTeamIds->contains($paper->team_id))->values(),
            );
        }

        // Reviewers (who are not the owner/admin) can only see papers from teams they were assigned to.
        if ($isReviewer && ! $user->isAdmin() && ! $isOwner) {
            $assignedTeamIds = $subject->teams->filter(
                fn ($team) => $team->members->contains('id', $user->id),
            )->pluck('id');

            $subject->setRelation(
                'papers',
                $subject->papers->filter(fn ($paper) => $assignedTeamIds->contains($paper->team_id))->values(),
            );
        }

        $reviewProgress = $subject->papers->count() > 0
            ? $subject->papers->filter(fn ($p) => $p->reviews->where('is_submitted', true)->isNotEmpty())->count()
            : 0;

        return Inertia::render('subjects/Show', [
            'subject' => $subject,
            'stats' => [
                'students' => $subject->students->count(),
                'reviewers' => $subject->reviewers->count(),
                'papers' => $subject->papers->count(),
                'reviewed' => $reviewProgress,
            ],
        ]);
    }

    public function edit(Request $request, Subject $subject): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);

        return Inertia::render('subjects/Edit', [
            'subject' => $subject,
        ]);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return to_route('subjects.show', $subject)
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);

        $subject->load(['rubrics', 'papers']);

        foreach ($subject->rubrics as $rubric) {
            Storage::disk('private')->delete($rubric->pdf_path);
        }

        foreach ($subject->papers as $paper) {
            Storage::disk('private')->delete($paper->file_path);
        }

        $subject->delete();

        return to_route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    public function addStudent(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        SubjectBlockedEmail::where('subject_id', $subject->id)
            ->where('email', strtolower($user->email))
            ->delete();

        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $user->id],
            ['role' => 'student', 'status' => 'approved', 'role_label' => null],
        );

        // An enrolled student should have a student account — but never demote
        // an admin or a teacher who actually owns/teaches a subject.
        if (! $user->isAdmin() && ! $user->teachingSubjects()->exists()) {
            $user->update(['role' => 'student']);
        }

        return back()->with('success', $user->name.' has been added to the subject.');
    }

    public function removeStudent(Subject $subject, User $user): RedirectResponse
    {
        $authUser = request()->user();
        abort_unless($authUser->isAdmin() || $subject->teacher_id === $authUser->id, 403);

        SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->update(['status' => 'blocked']);

        SubjectBlockedEmail::updateOrCreate(
            ['subject_id' => $subject->id, 'email' => strtolower($user->email)],
            ['blocked_by' => $authUser->id],
        );

        $user->teams()
            ->whereHas('subject', fn ($q) => $q->where('id', $subject->id))
            ->each(fn ($team) => $team->members()->detach($user->id));

        return back()->with('success', 'Student removed from the subject.');
    }

    public function addReviewer(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            // Subject-level membership roles: Advisor, Technical examiner, Academic
            // examiner, or a Custom Review Panel role. fyp_instructor is reserved for
            // the subject owner and is not invitable.
            'committee_role' => ['required', 'string', 'in:advisor,technical_examiner,academic_examiner,custom'],
            'role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $roleLabel = match ($validated['committee_role']) {
            'advisor' => 'Advisor',
            'technical_examiner' => 'Technical examiner',
            'academic_examiner' => 'Academic examiner',
            default => trim((string) ($validated['role_label'] ?? '')),
        };
        abort_if($validated['committee_role'] === 'custom' && $roleLabel === '', 422, 'Role label is required for custom roles.');

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            SubjectBlockedEmail::where('subject_id', $subject->id)
                ->where('email', strtolower($user->email))
                ->delete();

            SubjectMember::updateOrCreate(
                ['subject_id' => $subject->id, 'user_id' => $user->id],
                [
                    'role' => $validated['committee_role'],
                    'status' => 'approved',
                    'role_label' => $roleLabel !== '' ? $roleLabel : null,
                ],
            );

            // A reviewer needs a teacher-level account to get the reviewer
            // experience. Promote a plain student account; never touch admins
            // or existing teachers.
            if ($user->role === 'student') {
                $user->update(['role' => 'teacher']);
            }

            // A failed notification email must never break the invite itself.
            try {
                Mail::to($user)->queue(new ReviewerAddedMail($user, $subject, $roleLabel !== '' ? $roleLabel : $validated['committee_role']));
            } catch (\Throwable $e) {
                Log::warning('Reviewer-added email failed to send', ['error' => $e->getMessage()]);
            }

            return back()->with('success', $user->name.' has been added as a reviewer.');
        } else {
            $invitation = SubjectInvitation::updateOrCreate(
                ['subject_id' => $subject->id, 'email' => $validated['email']],
                [
                    'committee_role' => $validated['committee_role'],
                    'role_label' => $roleLabel !== '' ? $roleLabel : null,
                    'token' => SubjectInvitation::generateToken(),
                    'accepted_at' => null,
                ],
            );

            try {
                Mail::to($validated['email'])->queue(new ReviewerInvitationMail($invitation));
            } catch (\Throwable $e) {
                Log::warning('Reviewer-invitation email failed to send', ['error' => $e->getMessage()]);
            }

            return back()->with('success', 'Invitation sent to '.$validated['email'].'.');
        }
    }

    public function resetJoinCode(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $subject->update([
            'join_code' => $this->generateUniqueSubjectCode('join_code'),
        ]);

        return back()->with('success', 'Student join code has been reset. The old student code can no longer be used.');
    }

    public function resetReviewerCode(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $subject->update([
            'reviewer_code' => $this->generateUniqueSubjectCode('reviewer_code'),
        ]);

        return back()->with('success', 'Reviewer join code has been reset. The old reviewer code can no longer be used.');
    }

    public function removeReviewer(Subject $subject, User $user): RedirectResponse
    {
        $authUser = request()->user();
        abort_unless($authUser->isAdmin() || $subject->teacher_id === $authUser->id, 403);

        SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->update(['status' => 'blocked']);

        SubjectBlockedEmail::updateOrCreate(
            ['subject_id' => $subject->id, 'email' => strtolower($user->email)],
            ['blocked_by' => $authUser->id],
        );

        $user->teams()
            ->whereHas('subject', fn ($q) => $q->where('id', $subject->id))
            ->each(fn ($team) => $team->members()->detach($user->id));

        return back()->with('success', 'Reviewer removed from the subject.');
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:8'],
        ]);

        $subject = Subject::where('join_code', strtoupper($validated['join_code']))->firstOrFail();

        $email = strtolower($request->user()->email);

        $isBlocked = SubjectBlockedEmail::where('subject_id', $subject->id)
            ->where('email', $email)
            ->exists();

        if ($isBlocked) {
            return back()->withErrors([
                'join_code' => 'You have been removed from this classroom and are not allowed to rejoin.',
            ]);
        }

        $status = $subject->require_approval ? 'pending' : 'approved';

        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $request->user()->id],
            ['role' => 'student', 'status' => $status, 'role_label' => null],
        );

        return to_route('subjects.show', $subject)
            ->with('success', $status === 'pending'
                ? 'Join request sent. Waiting for teacher approval.'
                : 'You have joined '.$subject->title.'!');
    }

    public function joinAsReviewer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reviewer_code' => ['required', 'string', 'max:8'],
            // Advisor (team supervisor) or Review Panel member (custom). Per-session
            // scoring roles are assigned from the defense session; fyp_instructor is
            // reserved for the subject owner. Neither is self-selectable here.
            'committee_role' => ['required', 'string', 'in:advisor,custom'],
            'role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $subject = Subject::where('reviewer_code', strtoupper($validated['reviewer_code']))->firstOrFail();

        abort_if($subject->teacher_id === $request->user()->id, 403, 'You cannot join a subject you own as a reviewer.');

        $email = strtolower($request->user()->email);

        $isBlocked = SubjectBlockedEmail::where('subject_id', $subject->id)
            ->where('email', $email)
            ->exists();

        if ($isBlocked) {
            return back()->withErrors([
                'reviewer_code' => 'You have been removed from this classroom and are not allowed to rejoin.',
            ]);
        }

        $roleLabel = match ($validated['committee_role']) {
            'advisor' => 'Advisor',
            default => trim((string) ($validated['role_label'] ?? '')),
        };
        abort_if($validated['committee_role'] === 'custom' && $roleLabel === '', 422, 'Role label is required for custom roles.');

        $status = $subject->require_approval ? 'pending' : 'approved';

        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $request->user()->id],
            [
                'role' => $validated['committee_role'],
                'status' => $status,
                'role_label' => $roleLabel !== '' ? $roleLabel : null,
            ],
        );

        // A reviewer needs a teacher-level account; promote a plain student.
        if ($request->user()->role === 'student') {
            $request->user()->update(['role' => 'teacher']);
        }

        return to_route('subjects.show', $subject)
            ->with('success', $status === 'pending'
                ? 'Join request sent. Waiting for teacher approval.'
                : 'You have joined '.$subject->title.' as a reviewer!');
    }

    private function generateUniqueSubjectCode(string $column): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Subject::where($column, $code)->exists());

        return $code;
    }

    public function approveMember(Request $request, Subject $subject, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $member = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->update(['status' => 'approved']);

        // An approved reviewer (non-student member) gets a teacher-level account.
        if ($member->role !== 'student' && $user->role === 'student') {
            $user->update(['role' => 'teacher']);
        }

        return back()->with('success', $user->name.' has been approved.');
    }

    public function rejectMember(Request $request, Subject $subject, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $subject->teacher_id === $request->user()->id, 403);

        $member = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->update(['status' => 'blocked']);

        SubjectBlockedEmail::updateOrCreate(
            ['subject_id' => $subject->id, 'email' => strtolower($user->email)],
            ['blocked_by' => $request->user()->id],
        );

        $user->teams()
            ->whereHas('subject', fn ($q) => $q->where('id', $subject->id))
            ->each(fn ($team) => $team->members()->detach($user->id));

        return back()->with('success', $user->name.' has been rejected.');
    }

    public function leave(Request $request, Subject $subject): RedirectResponse
    {
        $user = $request->user();

        abort_if($subject->teacher_id === $user->id, 403, 'You cannot leave a subject you own.');

        $subject->students()->detach($user->id);
        $subject->reviewers()->detach($user->id);

        $user->teams()
            ->whereHas('subject', fn ($q) => $q->where('id', $subject->id))
            ->each(fn ($team) => $team->members()->detach($user->id));

        return to_route('subjects.index')
            ->with('success', 'You have left the subject.');
    }

    private function createDefaultDefensePeriods(Subject $subject): void
    {
        $periods = [
            ['name' => 'Midterm Defense', 'type' => 'midterm', 'sequence' => 1],
            ['name' => 'Final Defense', 'type' => 'final', 'sequence' => 2],
        ];

        foreach ($periods as $period) {
            DefensePeriod::firstOrCreate(
                ['subject_id' => $subject->id, 'type' => $period['type']],
                [
                    'name' => $period['name'],
                    'sequence' => $period['sequence'],
                    'score_scale' => 'points_100',
                    'passing_score' => $subject->passing_score ?: 50,
                    'status' => 'setup',
                ],
            );
        }
    }

    private function ensureRoundAttempts(Subject $subject): void
    {
        $this->createDefaultDefensePeriods($subject);
        $subject->loadMissing('defensePeriods', 'teams');

        foreach ($subject->teams as $team) {
            foreach ($subject->defensePeriods as $period) {
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
    }
}
