<?php

namespace App\Http\Controllers;

use App\Models\DefenseAttempt;
use App\Models\Subject;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignedTeamsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $assignedTeams = $user->teams()
            ->whereHas('subject.reviewers', fn ($query) => $query->where('users.id', $user->id))
            ->with([
                'subject:id,title,passing_score,teacher_id',
                'subject.rubric:id,subject_id,defense_period_id,structure_json,status',
                'subject.reviewers:id',
                'members:id,name,email',
                'defenseAttempts.period.rubric:id,subject_id,defense_period_id,structure_json,status',
                'defenseAttempts.activeReviewerAssignments.reviewer:id,name,email',
                'defenseAttempts.papers' => fn ($query) => $query->latest()->with('reviews'),
                'papers' => fn ($query) => $query->latest()->with('reviews'),
            ])
            ->orderBy('teams.defense_date')
            ->orderBy('teams.defense_time')
            ->get()
            ->values();

        // Teams from subjects the user owns (teacher overview)
        $ownedSubjects = Subject::where('teacher_id', $user->id)
            ->with([
                'rubric:id,subject_id,defense_period_id,structure_json,status',
                'reviewers:id',
                'teams.members:id,name,email',
                'teams.defenseAttempts.period.rubric:id,subject_id,defense_period_id,structure_json,status',
                'teams.defenseAttempts.activeReviewerAssignments.reviewer:id,name,email',
                'teams.defenseAttempts.papers' => fn ($query) => $query->latest()->with('reviews'),
                'teams.papers' => fn ($query) => $query->latest()->with('reviews'),
            ])
            ->get(['id', 'title', 'passing_score']);

        $ownedTeams = $ownedSubjects->flatMap(function ($subject) {
            return $subject->teams->map(function ($team) use ($subject) {
                $attempt = $this->currentAttemptForTeam($team);
                $paper = $attempt?->papers->first() ?? $team->papers->first();
                $reviewerIds = $subject->reviewers->pluck('id');
                $assignedReviewerCount = $attempt?->activeReviewerAssignments->isNotEmpty()
                    ? $attempt->activeReviewerAssignments->count()
                    : $team->members->whereIn('id', $reviewerIds)->count();

                return [
                    'id' => $team->id,
                    'team_id' => $team->id,
                    'name' => $team->name,
                    'defense_date' => ($team->defense_date ?? $attempt?->defense_date)?->format('Y-m-d'),
                    'defense_time' => $team->defense_time ?? $attempt?->defense_time,
                    'defense_duration' => $team->defense_duration ?? $attempt?->defense_duration,
                    'defense_room' => $team->defense_room ?? $attempt?->defense_room,
                    'score_deadline_at' => ($team->score_deadline_at ?? $attempt?->score_deadline_at)?->toISOString(),
                    'results_released_at' => ($team->results_released_at ?? $attempt?->results_released_at)?->toISOString(),
                    'subject' => ['id' => $subject->id, 'title' => $subject->title, 'passing_score' => $subject->passing_score],
                    'members' => $team->members->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email])->values(),
                    'paper' => $paper ? [
                        'id' => $paper->id,
                        'final_score' => $paper->final_score,
                        'visibility_status' => $paper->visibility_status,
                    ] : null,
                    'review_summary' => [
                        'submitted' => $paper?->reviews->where('is_submitted', true)->count() ?? 0,
                        'total' => $assignedReviewerCount,
                        'released' => $team->results_released_at !== null || $attempt?->results_released_at !== null,
                    ],
                ];
            });
        })->values();

        $reviewingSubjects = $user->reviewingSubjects()
            ->with([
                'reviewers:id',
                'defensePeriods.attempts.team.members:id,name,email',
                'defensePeriods.attempts.papers' => fn ($query) => $query->latest(),
                'defensePeriods.attempts.reviewerAssignments' => fn ($query) => $query->where('reviewer_id', $user->id),
            ])
            ->get(['subjects.id', 'subjects.title', 'subjects.passing_score']);

        $availableTeams = $reviewingSubjects->flatMap(function ($subject) use ($user) {
            $reviewerIds = $subject->reviewers->pluck('id');

            return $subject->defensePeriods->flatMap(function ($period) use ($subject, $reviewerIds, $user) {
                return $period->attempts
                    ->filter(fn (DefenseAttempt $attempt) => $attempt->defense_date !== null && $attempt->defense_time !== null)
                    ->reject(fn (DefenseAttempt $attempt) => $attempt->reviewerAssignments
                        ->contains(fn ($assignment) => $assignment->reviewer_id === $user->id && $assignment->status === 'active'))
                    ->map(function (DefenseAttempt $attempt) use ($subject, $period, $reviewerIds) {
                        $paper = $attempt->papers->first();
                        $assignment = $attempt->reviewerAssignments->first();

                        return [
                            'id' => $attempt->id,
                            'team_id' => $attempt->team_id,
                            'name' => $attempt->team->name,
                            'attempt_label' => $attempt->label,
                            'attempt_type' => $attempt->attempt_type,
                            'assignment_status' => $assignment?->status,
                            'defense_date' => $attempt->defense_date?->format('Y-m-d'),
                            'defense_time' => $attempt->defense_time,
                            'defense_duration' => $attempt->defense_duration,
                            'defense_room' => $attempt->defense_room,
                            'score_deadline_at' => $attempt->score_deadline_at?->toISOString(),
                            'subject' => [
                                'id' => $subject->id,
                                'title' => $subject->title,
                                'passing_score' => $subject->passing_score,
                            ],
                            'period' => [
                                'id' => $period->id,
                                'name' => $period->name,
                                'type' => $period->type,
                            ],
                            'members' => $attempt->team->members
                                ->reject(fn ($member) => $reviewerIds->contains($member->id))
                                ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email])
                                ->values(),
                            'paper_status' => $paper ? 'available_after_approval' : 'not_uploaded',
                        ];
                    });
            });
        })
            ->sortBy(fn (array $team) => ($team['defense_date'] ?? '9999-12-31').($team['defense_time'] ?? '99:99').$team['name'])
            ->values();

        return Inertia::render('teams/AssignedTeams', [
            'teams' => $assignedTeams->map(function ($team) use ($user) {
                $attempt = $this->currentAttemptForTeam($team);
                $paper = $attempt?->papers->first() ?? $team->papers->first();
                $review = $paper?->reviews->firstWhere('reviewer_id', $user->id);
                $reviewerIds = $team->subject->reviewers->pluck('id');

                return [
                    'id' => $team->id,
                    'team_id' => $team->id,
                    'name' => $team->name,
                    'defense_date' => ($team->defense_date ?? $attempt?->defense_date)?->format('Y-m-d'),
                    'defense_time' => $team->defense_time ?? $attempt?->defense_time,
                    'defense_duration' => $team->defense_duration ?? $attempt?->defense_duration,
                    'defense_room' => $team->defense_room ?? $attempt?->defense_room,
                    'score_deadline_at' => ($team->score_deadline_at ?? $attempt?->score_deadline_at)?->toISOString(),
                    'results_released_at' => ($team->results_released_at ?? $attempt?->results_released_at)?->toISOString(),
                    'subject' => [
                        'id' => $team->subject->id,
                        'title' => $team->subject->title,
                        'passing_score' => $team->subject->passing_score,
                    ],
                    'criteria' => $attempt?->period?->rubric?->structure_json ?? $team->subject->rubric?->structure_json ?? [],
                    'members' => $team->members
                        ->reject(fn ($member) => $reviewerIds->contains($member->id))
                        ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email])
                        ->values(),
                    'paper'  => $paper ? ['id' => $paper->id, 'visibility_status' => $paper->visibility_status] : null,
                    'review' => $review ? [
                        'id'           => $review->id,
                        'is_submitted' => $review->is_submitted,
                        'locked_at'    => $review->locked_at?->toISOString(),
                        'unlocked_at'  => $review->unlocked_at?->toISOString(),
                        'scores_json'  => $review->scores_json,
                    ] : null,
                ];
            }),
            'ownedTeams' => $ownedTeams,
            'availableTeams' => $availableTeams,
        ]);
    }

    private function currentAttemptForTeam(Team $team): ?DefenseAttempt
    {
        $attempts = $team->relationLoaded('defenseAttempts')
            ? $team->defenseAttempts
            : $team->defenseAttempts()
                ->with(['period.rubric', 'activeReviewerAssignments.reviewer', 'papers.reviews'])
                ->get();

        $sortedAttempts = $attempts->sortByDesc('attempt_number');

        return $sortedAttempts->first(fn (DefenseAttempt $attempt) => $attempt->results_released_at === null)
            ?? $sortedAttempts->first();
    }
}
