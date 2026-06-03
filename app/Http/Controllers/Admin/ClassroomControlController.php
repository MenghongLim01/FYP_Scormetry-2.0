<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\Review;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomControlController extends Controller
{
    public function show(Subject $subject): Response
    {
        $subject->load([
            'teacher:id,name,email',
            'students:id,name,email',
            'reviewers:id,name,email',
            'pendingMembers.user:id,name,email,role,status',
            'teams' => fn ($query) => $query->orderBy('name'),
            'teams.members:id,name,email,role',
            'teams.advisor:id,name,email',
            'teams.defenseAttempts' => fn ($query) => $query->orderBy('defense_period_id')->orderBy('attempt_number'),
            'teams.defenseAttempts.period.rubric',
            'teams.defenseAttempts.activeReviewerAssignments.reviewer:id,name,email',
            'teams.defenseAttempts.reviews',
            'teams.defenseAttempts.papers.reviews.reviewer:id,name,email',
        ]);

        return Inertia::render('admin/ClassroomControl', [
            'subject' => $this->subjectPayload($subject),
            'ownerCandidates' => User::query()
                ->where('role', 'teacher')
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'availableUsers' => User::query()
                ->where('status', 'approved')
                ->where('is_blocked', false)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'teams' => $subject->teams->map(fn (Team $team) => $this->teamPayload($team))->values(),
            'attempts' => $subject->teams
                ->flatMap(fn (Team $team) => $team->defenseAttempts->map(fn (DefenseAttempt $attempt) => $this->attemptPayload($attempt)))
                ->values(),
            'reviews' => $subject->teams
                ->flatMap(fn (Team $team) => $team->defenseAttempts)
                ->flatMap(fn (DefenseAttempt $attempt) => $attempt->papers->flatMap(fn ($paper) => $paper->reviews))
                ->map(fn (Review $review) => $this->reviewPayload($review))
                ->values(),
            'stats' => $this->statsPayload($subject),
        ]);
    }

    public function updateOwner(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
        ]);

        $newOwner = User::query()
            ->whereKey($validated['teacher_id'])
            ->where('role', 'teacher')
            ->where('status', 'approved')
            ->firstOrFail();

        $previousOwnerId = $subject->teacher_id;

        $subject->update(['teacher_id' => $newOwner->id]);

        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $newOwner->id],
            ['role' => 'fyp_instructor', 'status' => 'approved', 'role_label' => 'FYP Instructor'],
        );

        if ($previousOwnerId !== $newOwner->id) {
            SubjectMember::query()
                ->where('subject_id', $subject->id)
                ->where('user_id', $previousOwnerId)
                ->where('role', 'fyp_instructor')
                ->update(['role' => 'advisor', 'role_label' => 'Advisor']);

            DefenseAttemptReviewer::query()
                ->where('reviewer_id', $previousOwnerId)
                ->where('committee_role', 'fyp_instructor')
                ->whereHas('attempt.period', fn ($query) => $query->where('subject_id', $subject->id))
                ->update(['committee_role' => 'advisor']);
        }

        $subject->teams()
            ->with('defenseAttempts')
            ->get()
            ->flatMap(fn (Team $team) => $team->defenseAttempts)
            ->each(fn (DefenseAttempt $attempt) => $attempt->ensureOwnerIsReviewer());

        return back()->with('success', $newOwner->name.' is now the FYP instructor for this subject.');
    }

    public function addMember(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'member_type' => ['required', 'in:student,reviewer'],
            'reviewer_role' => ['nullable', 'string', 'max:100'],
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

        $user = User::query()
            ->whereKey($validated['user_id'])
            ->where('status', 'approved')
            ->where('is_blocked', false)
            ->firstOrFail();

        if ($validated['member_type'] === 'reviewer' && ! $user->isTeacher()) {
            return back()->withErrors(['user_id' => 'Choose an approved teacher user for the review panel.']);
        }

        $role = $validated['member_type'] === 'student'
            ? 'student'
            : ($validated['reviewer_role'] ?: 'advisor');

        SubjectMember::updateOrCreate(
            ['subject_id' => $subject->id, 'user_id' => $user->id],
            ['role' => $role, 'status' => 'approved', 'role_label' => $this->displayRole($role)],
        );

        if ($validated['member_type'] === 'student' && ! empty($validated['team_id'])) {
            $team = $subject->teams()->whereKey($validated['team_id'])->firstOrFail();
            $team->members()->syncWithoutDetaching([$user->id]);
        }

        return back()->with('success', $user->name.' added to '.$subject->title.'.');
    }

    public function removeMember(Subject $subject, User $user): RedirectResponse
    {
        if ($subject->teacher_id === $user->id) {
            return back()->withErrors(['member' => 'The FYP instructor cannot be removed. Assign a different owner first.']);
        }

        $membership = SubjectMember::query()
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return back()->withErrors(['member' => 'This user is not a member of the subject.']);
        }

        $hasSubmittedReviews = Review::query()
            ->where('reviewer_id', $user->id)
            ->where('is_submitted', true)
            ->whereHas('defenseAttempt.period', fn ($query) => $query->where('subject_id', $subject->id))
            ->exists();

        if ($hasSubmittedReviews) {
            $membership->update(['status' => 'blocked']);

            return back()->with('success', $user->name.' was blocked from new access. Their submitted review history stayed saved.');
        }

        $membership->delete();

        $subject->teams()->each(function (Team $team) use ($user) {
            $team->members()->detach($user->id);
        });

        DefenseAttemptReviewer::query()
            ->where('reviewer_id', $user->id)
            ->whereHas('attempt.period', fn ($query) => $query->where('subject_id', $subject->id))
            ->whereDoesntHave('reviews', fn ($query) => $query->where('is_submitted', true))
            ->delete();

        return back()->with('success', $user->name.' removed from this subject.');
    }

    /** @return array<string, mixed> */
    private function subjectPayload(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'title' => $subject->title,
            'description' => $subject->description,
            'teacher_id' => $subject->teacher_id,
            'teacher' => $subject->teacher,
            'join_code' => $subject->join_code,
            'reviewer_code' => $subject->reviewer_code,
            'passing_score' => $subject->passing_score,
            'url' => route('subjects.show', $subject),
            'students' => $subject->students->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
            'reviewers' => $subject->reviewers->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'role_label' => $user->pivot->role_label,
            ])->values(),
            'pending_members' => $subject->pendingMembers->map(fn (SubjectMember $member) => [
                'id' => $member->id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function teamPayload(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'topic' => $team->topic,
            'advisor' => $team->advisor ? [
                'id' => $team->advisor->id,
                'name' => $team->advisor->name,
            ] : null,
            'members' => $team->members
                ->filter(fn (User $member) => $member->role === 'student')
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ])
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function attemptPayload(DefenseAttempt $attempt): array
    {
        $paper = $attempt->papers->sortByDesc('id')->first();
        $activeReviewerCount = $attempt->activeReviewerAssignments->count();
        $submittedReviews = $attempt->reviews->where('is_submitted', true)->count();

        return [
            'id' => $attempt->id,
            'team_id' => $attempt->team_id,
            'team_name' => $attempt->team?->name,
            'period_name' => $attempt->period?->name,
            'label' => $attempt->label,
            'status' => $attempt->status,
            'defense_date' => $attempt->defense_date?->format('Y-m-d'),
            'defense_time' => $attempt->defense_time ? substr($attempt->defense_time, 0, 5) : null,
            'defense_room' => $attempt->defense_room,
            'paper_id' => $paper?->id,
            'paper_url' => $paper ? route('papers.show', $paper) : null,
            'active_reviewers_count' => $activeReviewerCount,
            'submitted_reviews_count' => $submittedReviews,
            'score_deadline_at' => $attempt->score_deadline_at?->toISOString(),
            'results_released_at' => $attempt->results_released_at?->toISOString(),
            'final_score' => $attempt->effectiveFinalScore(),
            'override_score' => $paper?->final_score_override,
            'override_note' => $paper?->final_score_override_reason,
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(Review $review): array
    {
        $scores = is_array($review->scores_json) ? $review->scores_json : [];

        return [
            'id' => $review->id,
            'reviewer_name' => $review->reviewer?->name ?? 'Unknown reviewer',
            'team_name' => $review->defenseAttempt?->team?->name ?? 'Team',
            'period_name' => $review->defenseAttempt?->period?->name ?? 'Defense',
            'label' => $review->defenseAttempt?->label ?? null,
            'scores_count' => count($scores),
            'scores_json' => $scores,
            'comment' => $review->comment,
            'is_submitted' => $review->is_submitted,
            'locked_at' => $review->locked_at?->toISOString(),
            'unlocked_at' => $review->unlocked_at?->toISOString(),
            'url' => route('reviews.show', $review),
        ];
    }

    /** @return array<string, int> */
    private function statsPayload(Subject $subject): array
    {
        $attempts = $subject->teams->flatMap(fn (Team $team) => $team->defenseAttempts);

        return [
            'teams' => $subject->teams->count(),
            'students' => $subject->students->count(),
            'reviewers' => $subject->reviewers->count(),
            'attempts' => $attempts->count(),
            'missing_schedules' => $attempts->filter(fn (DefenseAttempt $attempt) => $attempt->defense_date === null || $attempt->defense_time === null)->count(),
            'missing_documents' => $attempts->filter(fn (DefenseAttempt $attempt) => $attempt->papers->isEmpty())->count(),
            'locked_reviews' => $attempts->sum(fn (DefenseAttempt $attempt) => $attempt->reviews->whereNotNull('locked_at')->count()),
        ];
    }

    private function displayRole(string $role): string
    {
        return match ($role) {
            'technical_examiner' => 'Technical examiner',
            'academic_examiner' => 'Academic examiner',
            'fyp_instructor' => 'FYP Instructor',
            'advisor' => 'Advisor',
            // Legacy data: surface the retired guest_panel role as Custom role.
            'guest_panel' => 'Custom role',
            default => str($role)->replace('_', ' ')->title()->toString(),
        };
    }
}
