<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewUnlockLog;
use App\Models\RubricChangeLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function reviewUnlocks(Request $request): Response
    {
        $logs = ReviewUnlockLog::with([
            'review:id,paper_id,reviewer_id',
            'review.paper:id,team_id,subject_id',
            'review.paper.subject:id,title',
            'team:id,name',
            'judge:id,name,email',
            'unlockedBy:id,name,email',
        ])
            ->latest()
            ->paginate(50)
            ->through(fn (ReviewUnlockLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'subject' => $log->review?->paper?->subject?->title,
                'team' => $log->team?->name,
                'judge' => $log->judge ? ['name' => $log->judge->name, 'email' => $log->judge->email] : null,
                'unlocked_by' => $log->unlockedBy ? ['name' => $log->unlockedBy->name, 'email' => $log->unlockedBy->email] : null,
                'reason' => $log->reason,
            ]);

        return Inertia::render('admin/AuditReviews', [
            'logs' => $logs,
        ]);
    }

    public function rubricChanges(Request $request): Response
    {
        $logs = RubricChangeLog::with([
            'rubric:id,subject_id,defense_period_id',
            'rubric.subject:id,title',
            'rubric.defensePeriod:id,name',
            'changedBy:id,name,email',
        ])
            ->latest()
            ->paginate(50)
            ->through(fn (RubricChangeLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'subject' => $log->rubric?->subject?->title,
                'defense_period' => $log->rubric?->defensePeriod?->name,
                'changed_by' => $log->changedBy ? ['name' => $log->changedBy->name, 'email' => $log->changedBy->email] : null,
                'reason' => $log->reason,
                'scoring_started' => (bool) $log->scoring_started,
                'criteria_before' => count($log->structure_before ?? []),
                'criteria_after' => count($log->structure_after ?? []),
            ]);

        return Inertia::render('admin/AuditRubrics', [
            'logs' => $logs,
        ]);
    }
}
