<?php

namespace App\Http\Controllers;

use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $actionItems = [];

        if ($user->isAdmin()) {
            $stats = [
                ['label' => 'Total Subjects', 'value' => Subject::count(), 'color' => 'blue'],
                ['label' => 'Total Papers', 'value' => Paper::count(), 'color' => 'indigo'],
                ['label' => 'Pending Approvals', 'value' => Rubric::where('status', 'pending_verification')->count(), 'color' => 'amber'],
                ['label' => 'Review Completed', 'value' => Paper::where('visibility_status', 'published')->count(), 'color' => 'green'],
            ];

            $recentPapers = Paper::with(['team:id,name', 'subject:id,title'])
                ->latest()
                ->take(8)
                ->get(['id', 'team_id', 'subject_id', 'final_score', 'visibility_status', 'created_at']);

            $actionItems = $this->buildAdminActionItems();
        } elseif ($user->isTeacher()) {
            $ownedSubjectIds = Subject::where('teacher_id', $user->id)->pluck('id');
            $reviewingSubjectIds = $user->reviewingSubjects()->pluck('subjects.id');
            $allSubjectIds = $ownedSubjectIds->merge($reviewingSubjectIds)->unique()->values();

            $stats = [
                ['label' => 'My Subjects', 'value' => $ownedSubjectIds->count(), 'color' => 'blue'],
                ['label' => 'Papers to Review', 'value' => Paper::whereIn('subject_id', $allSubjectIds)->whereDoesntHave('reviews', fn ($q) => $q->where('reviewer_id', $user->id)->where('is_submitted', true))->count(), 'color' => 'amber'],
                ['label' => 'Reviews Submitted', 'value' => Review::where('reviewer_id', $user->id)->where('is_submitted', true)->count(), 'color' => 'green'],
                ['label' => 'Pending Rubrics', 'value' => Rubric::whereIn('subject_id', $ownedSubjectIds)->where('status', 'pending_verification')->count(), 'color' => 'indigo'],
            ];

            $recentPapers = Paper::with(['team:id,name', 'subject:id,title'])
                ->whereIn('subject_id', $allSubjectIds)
                ->latest()
                ->take(8)
                ->get(['id', 'team_id', 'subject_id', 'final_score', 'visibility_status', 'created_at']);

            $actionItems = $this->buildTeacherActionItems($ownedSubjectIds);
        } else {
            $teamIds = $user->teams()->pluck('teams.id');

            $stats = [
                ['label' => 'Enrolled Subjects', 'value' => $user->enrolledSubjects()->count(), 'color' => 'blue'],
                ['label' => 'My Papers', 'value' => Paper::whereIn('team_id', $teamIds)->count(), 'color' => 'indigo'],
                ['label' => 'Draft Papers', 'value' => Paper::whereIn('team_id', $teamIds)->where('visibility_status', 'draft')->count(), 'color' => 'amber'],
                ['label' => 'Review Completed', 'value' => Paper::whereIn('team_id', $teamIds)->where('visibility_status', 'published')->count(), 'color' => 'green'],
            ];

            $recentPapers = Paper::with(['subject:id,title'])
                ->whereIn('team_id', $teamIds)
                ->latest()
                ->take(8)
                ->get(['id', 'team_id', 'subject_id', 'final_score', 'visibility_status', 'created_at']);

            $actionItems = $this->buildStudentActionItems($user, $teamIds);
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentPapers' => $recentPapers,
            'actionItems' => $actionItems,
        ]);
    }

    /**
     * Cross-subject roll-up for instructors: surface every "needs your attention"
     * count in one place so they don't have to open each subject to find work.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $ownedSubjectIds
     * @return array<int, array{key: string, label: string, count: int, color: string, href: string|null}>
     */
    private function buildTeacherActionItems($ownedSubjectIds): array
    {
        if ($ownedSubjectIds->isEmpty()) {
            return [];
        }

        $items = [];

        // Pending student / reviewer join requests
        $pendingMemberSubjectId = SubjectMember::whereIn('subject_id', $ownedSubjectIds)
            ->where('status', 'pending')
            ->value('subject_id');
        $pendingMemberCount = SubjectMember::whereIn('subject_id', $ownedSubjectIds)
            ->where('status', 'pending')
            ->count();
        if ($pendingMemberCount > 0) {
            $items[] = [
                'key' => 'pending_members',
                'label' => 'Pending member requests',
                'count' => $pendingMemberCount,
                'color' => 'amber',
                'href' => $pendingMemberSubjectId ? route('subjects.show', $pendingMemberSubjectId) : null,
            ];
        }

        // Pending reviewer assignment requests
        $teamIds = Team::whereIn('subject_id', $ownedSubjectIds)->pluck('id');
        $attemptIds = DefenseAttempt::whereIn('team_id', $teamIds)->pluck('id');
        $pendingReviewerCount = DefenseAttemptReviewer::whereIn('defense_attempt_id', $attemptIds)
            ->where('status', 'pending')
            ->count();
        $pendingReviewerSubjectId = DefenseAttemptReviewer::whereIn('defense_attempt_id', $attemptIds)
            ->where('defense_attempt_reviewers.status', 'pending')
            ->join('defense_attempts', 'defense_attempts.id', '=', 'defense_attempt_reviewers.defense_attempt_id')
            ->join('teams', 'teams.id', '=', 'defense_attempts.team_id')
            ->value('teams.subject_id');
        if ($pendingReviewerCount > 0) {
            $items[] = [
                'key' => 'pending_reviewer_requests',
                'label' => 'Reviewer assignment requests',
                'count' => $pendingReviewerCount,
                'color' => 'amber',
                'href' => $pendingReviewerSubjectId ? route('subjects.show', $pendingReviewerSubjectId) : null,
            ];
        }

        // Rounds with no scheduled date yet
        $unscheduledCount = DefenseAttempt::whereIn('team_id', $teamIds)
            ->whereNull('defense_date')
            ->count();
        $unscheduledSubjectId = DefenseAttempt::whereIn('defense_attempts.team_id', $teamIds)
            ->whereNull('defense_attempts.defense_date')
            ->join('teams', 'teams.id', '=', 'defense_attempts.team_id')
            ->value('teams.subject_id');
        if ($unscheduledCount > 0) {
            $items[] = [
                'key' => 'rounds_unscheduled',
                'label' => 'Rounds without a schedule',
                'count' => $unscheduledCount,
                'color' => 'orange',
                'href' => $unscheduledSubjectId ? route('subjects.show', $unscheduledSubjectId) : null,
            ];
        }

        // Submitted papers with no reviews submitted yet
        $awaitingScoreCount = Paper::whereIn('subject_id', $ownedSubjectIds)
            ->where('visibility_status', 'submitted')
            ->whereDoesntHave('reviews', fn ($q) => $q->where('is_submitted', true))
            ->count();
        $awaitingScoreSubjectId = Paper::whereIn('subject_id', $ownedSubjectIds)
            ->where('visibility_status', 'submitted')
            ->whereDoesntHave('reviews', fn ($q) => $q->where('is_submitted', true))
            ->value('subject_id');
        if ($awaitingScoreCount > 0) {
            $items[] = [
                'key' => 'papers_no_score',
                'label' => 'Papers awaiting first score',
                'count' => $awaitingScoreCount,
                'color' => 'red',
                'href' => $awaitingScoreSubjectId ? route('subjects.show', $awaitingScoreSubjectId) : null,
            ];
        }

        // Pending-verification rubrics
        $pendingRubricCount = Rubric::whereIn('subject_id', $ownedSubjectIds)
            ->where('status', 'pending_verification')
            ->count();
        $pendingRubricSubjectId = Rubric::whereIn('subject_id', $ownedSubjectIds)
            ->where('status', 'pending_verification')
            ->value('subject_id');
        if ($pendingRubricCount > 0) {
            $items[] = [
                'key' => 'rubrics_pending',
                'label' => 'Rubrics pending verification',
                'count' => $pendingRubricCount,
                'color' => 'indigo',
                'href' => $pendingRubricSubjectId ? route('subjects.show', $pendingRubricSubjectId) : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{key: string, label: string, count: int, color: string, href: string|null}>
     */
    private function buildAdminActionItems(): array
    {
        $items = [];

        $pendingUsers = User::where('status', 'pending')->count();
        if ($pendingUsers > 0) {
            $items[] = [
                'key' => 'pending_users',
                'label' => 'Pending user approvals',
                'count' => $pendingUsers,
                'color' => 'amber',
                'href' => route('admin.users.index'),
            ];
        }

        $pendingRubrics = Rubric::where('status', 'pending_verification')->count();
        if ($pendingRubrics > 0) {
            $items[] = [
                'key' => 'pending_rubrics',
                'label' => 'Rubrics pending verification',
                'count' => $pendingRubrics,
                'color' => 'indigo',
                'href' => null,
            ];
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $teamIds
     * @return array<int, array{key: string, label: string, count: int, color: string, href: string|null}>
     */
    private function buildStudentActionItems(User $user, $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        $items = [];

        // Upcoming defenses with no paper yet
        $missingPaperCount = DefenseAttempt::whereIn('team_id', $teamIds)
            ->whereNotNull('defense_date')
            ->where('defense_date', '>=', now()->toDateString())
            ->whereDoesntHave('papers')
            ->count();
        if ($missingPaperCount > 0) {
            $items[] = [
                'key' => 'upload_paper',
                'label' => 'Upcoming defenses missing a paper',
                'count' => $missingPaperCount,
                'color' => 'red',
                'href' => null,
            ];
        }

        return $items;
    }
}
