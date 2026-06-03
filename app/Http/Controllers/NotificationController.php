<?php

namespace App\Http\Controllers;

use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(30)
            ->through(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'category' => $n->data['category'] ?? 'system',
                'priority' => 'info',
                'status' => $n->read_at ? 'Read' : 'New',
                'source' => 'notification',
                'action_label' => ($n->data['url'] ?? null) ? 'Open' : null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'actionItems' => $this->actionItemsFor($request->user()),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actionItemsFor(User $user): array
    {
        return collect()
            ->merge($this->instructorActionItems($user))
            ->merge($this->reviewerActionItems($user))
            ->merge($this->studentActionItems($user))
            ->merge($this->adminActionItems($user))
            ->sortBy(fn (array $item): int => ($this->priorityRank($item['priority']) * 10000000000) + ($item['sort_at'] ?? 9999999999))
            ->take(30)
            ->values()
            ->map(function (array $item): array {
                unset($item['sort_at']);

                return $item;
            })
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function instructorActionItems(User $user): Collection
    {
        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return collect();
        }

        $items = collect();

        Subject::query()
            ->where('teacher_id', $user->id)
            ->withCount('pendingMembers')
            ->having('pending_members_count', '>', 0)
            ->limit(10)
            ->get()
            ->each(function (Subject $subject) use ($items): void {
                $items->push($this->makeActionItem(
                    id: "subject-{$subject->id}-member-requests",
                    title: 'Member requests pending',
                    body: "{$subject->pending_members_count} people are waiting for approval in {$subject->title}.",
                    url: route('subjects.show', $subject),
                    category: 'system',
                    priority: 'warning',
                    status: 'Needs action',
                    actionLabel: 'Review members',
                ));
            });

        $attempts = DefenseAttempt::query()
            ->whereHas('team.subject', fn ($query) => $query->where('teacher_id', $user->id))
            ->with([
                'period',
                'team.subject',
                'team.members',
                'papers.reviews',
                'reviewerAssignments',
                'activeReviewerAssignments',
            ])
            ->latest('updated_at')
            ->limit(250)
            ->get();

        $attempts->each(function (DefenseAttempt $attempt) use ($items): void {
            $subject = $attempt->team?->subject;
            $team = $attempt->team;

            if (! $subject || ! $team) {
                return;
            }

            $attemptLabel = $this->attemptLabel($attempt);
            $paper = $this->latestPaper($attempt);
            $activeReviewers = $attempt->activeReviewerAssignments->count();
            $submittedReviews = $paper?->reviews->where('is_submitted', true)->count() ?? 0;
            $pendingReviewers = $attempt->reviewerAssignments->where('status', 'pending')->count();

            if ($pendingReviewers > 0) {
                $items->push($this->makeActionItem(
                    id: "attempt-{$attempt->id}-reviewer-requests",
                    title: 'Reviewer request pending',
                    body: "{$pendingReviewers} reviewer request(s) need approval for {$team->name} in {$attemptLabel}.",
                    url: route('subjects.show', $subject),
                    category: 'reviewer',
                    priority: 'warning',
                    status: 'Needs action',
                    actionLabel: 'Approve request',
                    sortAt: $attempt->updated_at,
                ));
            }

            if (! $attempt->defense_date || ! $attempt->defense_time) {
                $items->push($this->makeActionItem(
                    id: "attempt-{$attempt->id}-schedule",
                    title: 'Defense schedule missing',
                    body: "{$team->name} needs date, time, duration, and room for {$attemptLabel}.",
                    url: route('subjects.show', $subject),
                    category: 'schedule',
                    priority: 'warning',
                    status: 'Needs action',
                    actionLabel: 'Set schedule',
                    sortAt: $attempt->updated_at,
                ));
            }

            if (! $paper && $this->isUploadAttentionNeeded($attempt)) {
                $isOverdue = $attempt->paper_upload_deadline_at?->isPast() ?? false;

                $items->push($this->makeActionItem(
                    id: "attempt-{$attempt->id}-missing-document",
                    title: $isOverdue ? 'Document overdue' : 'Document still missing',
                    body: "{$team->name} has no PDF for {$attemptLabel}.",
                    url: route('subjects.show', $subject),
                    category: 'paper',
                    priority: $isOverdue ? 'danger' : 'warning',
                    status: $isOverdue ? 'Overdue' : 'Needs action',
                    actionLabel: 'Check document',
                    sortAt: $attempt->paper_upload_deadline_at,
                ));
            }

            if ($activeReviewers > 0 && $attempt->score_deadline_at?->isPast() && $submittedReviews < $activeReviewers) {
                $items->push($this->makeActionItem(
                    id: "attempt-{$attempt->id}-score-overdue",
                    title: 'Judge score overdue',
                    body: "{$team->name} has {$submittedReviews}/{$activeReviewers} submitted reviews for {$attemptLabel}.",
                    url: route('subjects.show', $subject),
                    category: 'deadline',
                    priority: 'danger',
                    status: 'Overdue',
                    actionLabel: 'View scores',
                    sortAt: $attempt->score_deadline_at,
                ));
            }

            if ($paper && $activeReviewers > 0 && $submittedReviews >= $activeReviewers && ! $attempt->results_released_at) {
                $items->push($this->makeActionItem(
                    id: "attempt-{$attempt->id}-ready-release",
                    title: 'Result ready to release',
                    body: "{$team->name} has all {$submittedReviews} review(s) submitted for {$attemptLabel}.",
                    url: route('teams.scores', $team),
                    category: 'result',
                    priority: 'info',
                    status: 'Ready',
                    actionLabel: 'Review result',
                    sortAt: $attempt->score_deadline_at ?? $attempt->updated_at,
                ));
            }
        });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function reviewerActionItems(User $user): Collection
    {
        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return collect();
        }

        $items = collect();

        DefenseAttemptReviewer::query()
            ->where('reviewer_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'rejected'])
            ->with([
                'attempt.period',
                'attempt.team.subject',
                'attempt.team.members',
                'attempt.papers.reviews',
            ])
            ->latest('updated_at')
            ->limit(120)
            ->get()
            ->each(function (DefenseAttemptReviewer $assignment) use ($items, $user): void {
                $attempt = $assignment->attempt;
                $team = $attempt?->team;
                $subject = $team?->subject;

                if (! $attempt || ! $team || ! $subject) {
                    return;
                }

                $attemptLabel = $this->attemptLabel($attempt);

                if ($assignment->status === 'pending') {
                    $items->push($this->makeActionItem(
                        id: "assignment-{$assignment->id}-pending",
                        title: 'Reviewer approval pending',
                        body: "Your request for {$team->name} in {$attemptLabel} is waiting for the FYP instructor.",
                        url: route('teams.assigned'),
                        category: 'reviewer',
                        priority: 'info',
                        status: 'Waiting',
                        actionLabel: 'View request',
                        sortAt: $assignment->updated_at,
                    ));

                    return;
                }

                if ($assignment->status === 'rejected') {
                    $items->push($this->makeActionItem(
                        id: "assignment-{$assignment->id}-rejected",
                        title: 'Reviewer request rejected',
                        body: "Your request for {$team->name} in {$attemptLabel} was not approved.",
                        url: route('teams.assigned'),
                        category: 'reviewer',
                        priority: 'info',
                        status: 'Rejected',
                        actionLabel: 'View teams',
                        sortAt: $assignment->updated_at,
                    ));

                    return;
                }

                $paper = $this->latestPaper($attempt);
                if (! $paper) {
                    return;
                }

                $review = $paper->reviews->firstWhere('reviewer_id', $user->id);
                if ($review?->unlocked_at && ! $review->locked_at) {
                    $items->push($this->makeActionItem(
                        id: "review-{$review->id}-unlocked",
                        title: 'Review unlocked for correction',
                        body: "{$team->name} in {$attemptLabel} is open again for your correction.",
                        url: route('reviews.create', $paper),
                        category: 'review',
                        priority: 'warning',
                        status: 'Needs action',
                        actionLabel: 'Correct score',
                        sortAt: $review->unlocked_at,
                    ));

                    return;
                }

                if ($review?->is_submitted) {
                    return;
                }

                $priority = 'info';
                $status = 'Needs action';

                if ($attempt->score_deadline_at?->isPast()) {
                    $priority = 'danger';
                    $status = 'Overdue';
                } elseif ($attempt->score_deadline_at?->lessThanOrEqualTo(now()->addDay())) {
                    $priority = 'warning';
                    $status = 'Due soon';
                }

                $items->push($this->makeActionItem(
                    id: "assignment-{$assignment->id}-score",
                    title: 'Score this defense',
                    body: "{$team->name} has a submitted document for {$attemptLabel}.",
                    url: route('reviews.create', $paper),
                    category: 'review',
                    priority: $priority,
                    status: $status,
                    actionLabel: 'Open scoring',
                    sortAt: $attempt->score_deadline_at ?? $attempt->updated_at,
                ));
            });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function studentActionItems(User $user): Collection
    {
        if (! $user->isStudent()) {
            return collect();
        }

        $items = collect();

        Team::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
            ->with(['subject', 'members', 'defenseAttempts.period', 'defenseAttempts.papers'])
            ->limit(80)
            ->get()
            ->each(function (Team $team) use ($items): void {
                $subject = $team->subject;

                if (! $subject) {
                    return;
                }

                $team->defenseAttempts->each(function (DefenseAttempt $attempt) use ($items, $team, $subject): void {
                    $attemptLabel = $this->attemptLabel($attempt);
                    $paper = $this->latestPaper($attempt);

                    if ($attempt->results_released_at && $attempt->results_released_at->greaterThanOrEqualTo(now()->subDays(14))) {
                        $items->push($this->makeActionItem(
                            id: "attempt-{$attempt->id}-student-result",
                            title: 'Result released',
                            body: "Your {$attemptLabel} result for {$team->name} is available.",
                            url: route('teams.result', $team),
                            category: 'result',
                            priority: 'success',
                            status: 'Released',
                            actionLabel: 'View result',
                            sortAt: $attempt->results_released_at,
                        ));
                    }

                    if (! $paper && $attempt->paper_upload_deadline_at) {
                        $isOverdue = $attempt->paper_upload_deadline_at->isPast();
                        $isDueSoon = $attempt->paper_upload_deadline_at->lessThanOrEqualTo(now()->addDays(2));

                        if ($isOverdue || $isDueSoon) {
                            $items->push($this->makeActionItem(
                                id: "attempt-{$attempt->id}-student-upload",
                                title: $isOverdue ? 'Document upload overdue' : 'Upload your defense document',
                                body: "{$team->name} still needs a PDF for {$attemptLabel}.",
                                url: route('papers.create', $subject),
                                category: 'paper',
                                priority: $isOverdue ? 'danger' : 'warning',
                                status: $isOverdue ? 'Overdue' : 'Due soon',
                                actionLabel: 'Upload PDF',
                                sortAt: $attempt->paper_upload_deadline_at,
                            ));
                        }
                    }

                    if ($attempt->defense_date?->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay())) {
                        $items->push($this->makeActionItem(
                            id: "attempt-{$attempt->id}-student-schedule",
                            title: 'Defense coming up',
                            body: "{$team->name} is scheduled for {$attemptLabel} on {$attempt->defense_date->format('M j, Y')}.",
                            url: route('subjects.show', $subject),
                            category: 'schedule',
                            priority: 'info',
                            status: 'Scheduled',
                            actionLabel: 'View schedule',
                            sortAt: $attempt->defense_date,
                        ));
                    }
                });
            });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function adminActionItems(User $user): Collection
    {
        if (! $user->isAdmin()) {
            return collect();
        }

        $pendingUsers = User::query()->where('status', 'pending')->count();

        if ($pendingUsers === 0) {
            return collect();
        }

        return collect([
            $this->makeActionItem(
                id: 'admin-pending-users',
                title: 'Users waiting for approval',
                body: "{$pendingUsers} user account(s) need admin approval.",
                url: route('admin.users.index'),
                category: 'system',
                priority: 'warning',
                status: 'Needs action',
                actionLabel: 'Review users',
            ),
        ]);
    }

    private function latestPaper(DefenseAttempt $attempt): ?Paper
    {
        /** @var EloquentCollection<int, Paper> $papers */
        $papers = $attempt->papers;

        return $papers->sortByDesc('created_at')->first();
    }

    private function attemptLabel(DefenseAttempt $attempt): string
    {
        return trim(($attempt->period?->name ?? 'Defense').' - '.($attempt->label ?? 'Attempt '.$attempt->attempt_number));
    }

    private function isUploadAttentionNeeded(DefenseAttempt $attempt): bool
    {
        if ($attempt->paper_upload_deadline_at) {
            return $attempt->paper_upload_deadline_at->lessThanOrEqualTo(now()->addDays(2));
        }

        if ($attempt->defense_date) {
            return $attempt->defense_date->lessThanOrEqualTo(now()->addDays(3));
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeActionItem(
        string $id,
        string $title,
        string $body,
        ?string $url,
        string $category,
        string $priority,
        string $status,
        string $actionLabel,
        ?CarbonInterface $sortAt = null,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'source' => 'task',
            'action_label' => $actionLabel,
            'read_at' => null,
            'created_at' => $sortAt?->toIso8601String(),
            'sort_at' => $sortAt?->timestamp ?? now()->timestamp,
        ];
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'danger' => 0,
            'warning' => 1,
            'info' => 2,
            'success' => 3,
            default => 4,
        };
    }
}
