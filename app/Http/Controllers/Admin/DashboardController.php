<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefenseAttempt;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'total_classrooms' => Subject::count(),
                'total_users' => User::count(),
                'total_submissions' => Paper::count(),
                'pending_approvals' => SubjectMember::where('status', 'pending')->count(),
                'missing_schedules' => DefenseAttempt::whereNull('defense_date')->orWhereNull('defense_time')->count(),
                'missing_documents' => DefenseAttempt::whereDoesntHave('papers')->count(),
                'overdue_reviews' => $this->overdueReviewAttemptsCount(),
                'ready_to_release' => $this->readyToReleaseAttemptsCount(),
                'unlocked_reviews' => Review::whereNotNull('unlocked_at')->whereNull('locked_at')->count(),
                'pending_users' => User::where('status', 'pending')->count(),
            ],
        ]);
    }

    private function overdueReviewAttemptsCount(): int
    {
        return DefenseAttempt::query()
            ->withCount([
                'activeReviewerAssignments as active_reviewers_count',
                'reviews as submitted_reviews_count' => fn ($query) => $query->where('is_submitted', true),
            ])
            ->whereNotNull('score_deadline_at')
            ->where('score_deadline_at', '<', now())
            ->get()
            ->filter(fn (DefenseAttempt $attempt) => $attempt->active_reviewers_count > $attempt->submitted_reviews_count)
            ->count();
    }

    private function readyToReleaseAttemptsCount(): int
    {
        return DefenseAttempt::query()
            ->withCount([
                'activeReviewerAssignments as active_reviewers_count',
                'reviews as submitted_reviews_count' => fn ($query) => $query->where('is_submitted', true),
                'papers as papers_count',
            ])
            ->whereNull('results_released_at')
            ->get()
            ->filter(fn (DefenseAttempt $attempt) => $attempt->papers_count > 0
                && $attempt->active_reviewers_count > 0
                && $attempt->submitted_reviews_count >= $attempt->active_reviewers_count)
            ->count();
    }
}
