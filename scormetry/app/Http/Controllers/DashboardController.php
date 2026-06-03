<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

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
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentPapers' => $recentPapers,
        ]);
    }
}
