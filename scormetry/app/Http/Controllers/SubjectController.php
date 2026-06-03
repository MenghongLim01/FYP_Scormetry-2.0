<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Mail\ReviewerAddedMail;
use App\Mail\ReviewerInvitationMail;
use App\Models\DefensePeriod;
use App\Models\Subject;
use App\Models\SubjectBlockedEmail;
use App\Models\SubjectInvitation;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $subjects = match (true) {
            $user->isAdmin() => Subject::with('teacher')
                ->withCount(['students', 'papers', 'reviewers'])
                ->latest()
                ->get(),
            $user->isTeacher() => Subject::with('teacher')
                ->withCount(['students', 'papers', 'reviewers'])
                ->where(function ($query) use ($user) {
                    $query->where('teacher_id', $user->id)
                        ->orWhereHas('reviewers', fn ($q) => $q->where('users.id', $user->id));
                })
                ->latest()
                ->get(),
            default => $user->enrolledSubjects()->with('teacher')
                ->withCount(['students', 'papers'])
                ->latest()
                ->get(),
        };

        return Inertia::render('subjects/Index', [
            'subjects' => $subjects,
        ]);
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
            'defensePeriods.attempts.papers.reviews.reviewer',
            'defensePeriods.attempts.reviewerAssignments.reviewer',
            'defensePeriods.attempts.activeReviewerAssignments.reviewer',
            'teams.members',
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
            'committee_role' => ['required', 'string', 'in:advisor,fyp_instructor,guest_panel,custom'],
            'role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $roleLabel = match ($validated['committee_role']) {
            'advisor' => 'Advisor',
            'fyp_instructor' => 'FYP Instructor',
            'guest_panel' => 'Guest Panel',
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

            Mail::to($user)->send(new ReviewerAddedMail($user, $subject, $roleLabel !== '' ? $roleLabel : $validated['committee_role']));

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

            Mail::to($validated['email'])->send(new ReviewerInvitationMail($invitation));

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
            'committee_role' => ['required', 'string', 'in:advisor,fyp_instructor,guest_panel,custom'],
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
            'fyp_instructor' => 'FYP Instructor',
            'guest_panel' => 'Guest Panel',
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
