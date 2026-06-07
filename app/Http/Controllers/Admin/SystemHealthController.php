<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefenseAttempt;
use App\Models\DefensePeriod;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\Paper;
use App\Models\Review;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/SystemHealth', [
            'summary' => $this->summary(),
            'sections' => [
                $this->pendingUsersSection(),
                $this->pendingApprovalsSection(),
                $this->missingSchedulesSection(),
                $this->missingDocumentsSection(),
                $this->missingRubricsSection(),
                $this->overdueReviewsSection(),
                $this->readyToReleaseSection(),
                $this->unlockedReviewsSection(),
                $this->missingFilesSection(),
                $this->mailQueueSection(),
                $this->calendarConnectivitySection(),
            ],
        ]);
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        return [
            'pending_users' => User::where('status', 'pending')->count(),
            'pending_approvals' => SubjectMember::where('status', 'pending')->count(),
            'missing_schedules' => DefenseAttempt::whereNull('defense_date')->orWhereNull('defense_time')->count(),
            'missing_documents' => DefenseAttempt::whereDoesntHave('papers')->count(),
            'unlocked_reviews' => Review::whereNotNull('unlocked_at')->whereNull('locked_at')->count(),
            'queued_jobs' => $this->tableCount('jobs'),
            'failed_jobs' => $this->tableCount('failed_jobs'),
            'calendar_connections' => GoogleCalendarConnection::whereNull('disconnected_at')
                ->whereNotNull('refresh_token')
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function pendingUsersSection(): array
    {
        $users = User::query()
            ->where('status', 'pending')
            ->latest()
            ->limit(8)
            ->get(['id', 'name', 'email', 'role']);

        return [
            'key' => 'pending-users',
            'title' => 'Pending user approvals',
            'description' => 'Users waiting for admin approval before they can use Scormetry.',
            'severity' => 'warning',
            'count' => User::where('status', 'pending')->count(),
            'action_label' => 'Review users',
            'action_url' => route('admin.users.index'),
            'items' => $users->map(fn (User $user) => [
                'title' => $user->name,
                'meta' => $user->email.' · '.$user->role,
                'url' => route('admin.users.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function pendingApprovalsSection(): array
    {
        $memberships = SubjectMember::query()
            ->with(['subject:id,title', 'user:id,name,email'])
            ->where('status', 'pending')
            ->latest()
            ->limit(8)
            ->get();

        return [
            'key' => 'pending-members',
            'title' => 'Subject join requests',
            'description' => 'Students or reviewers waiting for a subject owner or admin decision.',
            'severity' => 'warning',
            'count' => SubjectMember::where('status', 'pending')->count(),
            'action_label' => 'Open classrooms',
            'action_url' => route('admin.classrooms.index'),
            'items' => $memberships->map(fn (SubjectMember $membership) => [
                'title' => $membership->user?->name ?? 'Unknown user',
                'meta' => ($membership->subject?->title ?? 'Unknown subject').' · '.$membership->role,
                'url' => $membership->subject ? route('admin.classrooms.control', $membership->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function missingSchedulesSection(): array
    {
        $attempts = DefenseAttempt::query()
            ->with(['period:id,name', 'team:id,name,subject_id', 'team.subject:id,title'])
            ->whereNull('defense_date')
            ->orWhereNull('defense_time')
            ->latest()
            ->limit(8)
            ->get();

        return [
            'key' => 'missing-schedules',
            'title' => 'Defense sessions without schedule',
            'description' => 'Teams that still need date, time, duration, and room before calendar invites can be sent.',
            'severity' => 'danger',
            'count' => DefenseAttempt::whereNull('defense_date')->orWhereNull('defense_time')->count(),
            'action_label' => 'Fix schedules',
            'action_url' => route('admin.classrooms.index'),
            'items' => $attempts->map(fn (DefenseAttempt $attempt) => [
                'title' => ($attempt->team?->name ?? 'Team').' · '.($attempt->period?->name ?? 'Defense'),
                'meta' => $attempt->team?->subject?->title ?? 'Unknown subject',
                'url' => $attempt->team?->subject ? route('admin.classrooms.control', $attempt->team->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function missingDocumentsSection(): array
    {
        $attempts = DefenseAttempt::query()
            ->with(['period:id,name', 'team:id,name,subject_id', 'team.subject:id,title'])
            ->whereDoesntHave('papers')
            ->latest()
            ->limit(8)
            ->get();

        return [
            'key' => 'missing-documents',
            'title' => 'Defense sessions without documents',
            'description' => 'Attempts where reviewers cannot see a student PDF yet.',
            'severity' => 'warning',
            'count' => DefenseAttempt::whereDoesntHave('papers')->count(),
            'action_label' => 'Open controls',
            'action_url' => route('admin.classrooms.index'),
            'items' => $attempts->map(fn (DefenseAttempt $attempt) => [
                'title' => ($attempt->team?->name ?? 'Team').' · '.($attempt->period?->name ?? 'Defense'),
                'meta' => $attempt->team?->subject?->title ?? 'Unknown subject',
                'url' => $attempt->team?->subject ? route('admin.classrooms.control', $attempt->team->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function missingRubricsSection(): array
    {
        $periods = DefensePeriod::query()
            ->with('subject:id,title')
            ->whereDoesntHave('rubric')
            ->orderBy('sequence')
            ->limit(8)
            ->get();

        return [
            'key' => 'missing-rubrics',
            'title' => 'Defense periods without rubric',
            'description' => 'Midterm or final periods that still need a locked rubric before clean scoring.',
            'severity' => 'warning',
            'count' => DefensePeriod::whereDoesntHave('rubric')->count(),
            'action_label' => 'Open classrooms',
            'action_url' => route('admin.classrooms.index'),
            'items' => $periods->map(fn (DefensePeriod $period) => [
                'title' => $period->name,
                'meta' => $period->subject?->title ?? 'Unknown subject',
                'url' => $period->subject ? route('admin.classrooms.control', $period->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function overdueReviewsSection(): array
    {
        $attempts = DefenseAttempt::query()
            ->with(['period:id,name', 'team:id,name,subject_id', 'team.subject:id,title'])
            ->withCount([
                'activeReviewerAssignments as active_reviewers_count',
                'reviews as submitted_reviews_count' => fn ($query) => $query->where('is_submitted', true),
            ])
            ->whereNotNull('score_deadline_at')
            ->where('score_deadline_at', '<', now())
            ->latest()
            ->get()
            ->filter(fn (DefenseAttempt $attempt) => $attempt->active_reviewers_count > $attempt->submitted_reviews_count);

        return [
            'key' => 'overdue-reviews',
            'title' => 'Overdue judge reviews',
            'description' => 'Score deadlines have passed, but not all assigned reviewers submitted.',
            'severity' => 'danger',
            'count' => $attempts->count(),
            'action_label' => 'Review overdue work',
            'action_url' => route('admin.classrooms.index'),
            'items' => $attempts->take(8)->map(fn (DefenseAttempt $attempt) => [
                'title' => ($attempt->team?->name ?? 'Team').' · '.($attempt->period?->name ?? 'Defense'),
                'meta' => $attempt->submitted_reviews_count.'/'.$attempt->active_reviewers_count.' submitted',
                'url' => $attempt->team?->subject ? route('admin.classrooms.control', $attempt->team->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function readyToReleaseSection(): array
    {
        $attempts = DefenseAttempt::query()
            ->with(['period:id,name', 'team:id,name,subject_id', 'team.subject:id,title'])
            ->withCount([
                'activeReviewerAssignments as active_reviewers_count',
                'reviews as submitted_reviews_count' => fn ($query) => $query->where('is_submitted', true),
                'papers as papers_count',
            ])
            ->whereNull('results_released_at')
            ->latest()
            ->get()
            ->filter(fn (DefenseAttempt $attempt) => $attempt->papers_count > 0
                && $attempt->active_reviewers_count > 0
                && $attempt->submitted_reviews_count >= $attempt->active_reviewers_count);

        return [
            'key' => 'ready-release',
            'title' => 'Results ready to release',
            'description' => 'All assigned reviews are submitted and the instructor can publish the result.',
            'severity' => 'success',
            'count' => $attempts->count(),
            'action_label' => 'Open controls',
            'action_url' => route('admin.classrooms.index'),
            'items' => $attempts->take(8)->map(fn (DefenseAttempt $attempt) => [
                'title' => ($attempt->team?->name ?? 'Team').' · '.($attempt->period?->name ?? 'Defense'),
                'meta' => $attempt->submitted_reviews_count.'/'.$attempt->active_reviewers_count.' submitted',
                'url' => $attempt->team?->subject ? route('admin.classrooms.control', $attempt->team->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function unlockedReviewsSection(): array
    {
        $reviews = Review::query()
            ->with(['reviewer:id,name', 'defenseAttempt.period:id,name', 'defenseAttempt.team:id,name,subject_id', 'defenseAttempt.team.subject:id,title'])
            ->whereNotNull('unlocked_at')
            ->whereNull('locked_at')
            ->latest('unlocked_at')
            ->limit(8)
            ->get();

        return [
            'key' => 'unlocked-reviews',
            'title' => 'Reviews open for correction',
            'description' => 'Submitted reviews that were unlocked and still need correction/resubmission.',
            'severity' => 'warning',
            'count' => Review::whereNotNull('unlocked_at')->whereNull('locked_at')->count(),
            'action_label' => 'View audit log',
            'action_url' => route('admin.audit.reviews'),
            'items' => $reviews->map(fn (Review $review) => [
                'title' => $review->reviewer?->name ?? 'Reviewer',
                'meta' => ($review->defenseAttempt?->team?->name ?? 'Team').' · '.($review->defenseAttempt?->period?->name ?? 'Defense'),
                'url' => $review->defenseAttempt?->team?->subject ? route('admin.classrooms.control', $review->defenseAttempt->team->subject) : route('admin.audit.reviews'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function missingFilesSection(): array
    {
        $papers = Paper::query()
            ->with(['subject:id,title', 'team:id,name'])
            ->latest()
            ->limit(300)
            ->get()
            ->filter(fn (Paper $paper) => ! Storage::disk('private')->exists($paper->file_path));

        return [
            'key' => 'missing-files',
            'title' => 'Database papers with missing PDF files',
            'description' => 'Records pointing to files that no longer exist in private storage.',
            'severity' => 'danger',
            'count' => $papers->count(),
            'action_label' => 'Open controls',
            'action_url' => route('admin.classrooms.index'),
            'items' => $papers->take(8)->map(fn (Paper $paper) => [
                'title' => $paper->team?->name ?? 'Paper #'.$paper->id,
                'meta' => ($paper->subject?->title ?? 'Unknown subject').' · '.$paper->file_path,
                'url' => $paper->subject ? route('admin.classrooms.control', $paper->subject) : route('admin.classrooms.index'),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function mailQueueSection(): array
    {
        $mailer = (string) config('mail.default');
        $queue = (string) config('queue.default');
        $pendingJobs = $this->tableCount('jobs');
        $failedJobs = $this->tableCount('failed_jobs');
        $staleReadyJobs = $this->staleReadyJobsCount();
        $issueCount = 0;

        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            $issueCount++;
        }

        if ($failedJobs > 0) {
            $issueCount++;
        }

        if ($staleReadyJobs > 0) {
            $issueCount++;
        }

        return [
            'key' => 'mail-queue-readiness',
            'title' => 'Email and queue readiness',
            'description' => 'Schedule invites are queued mail, so production needs a real mailer and a running queue worker.',
            'severity' => $issueCount > 0 ? 'danger' : 'success',
            'count' => $issueCount,
            'action_label' => 'Open settings',
            'action_url' => route('admin.settings.edit'),
            'items' => [
                [
                    'title' => 'Mail driver',
                    'meta' => in_array($mailer, ['log', 'array', 'null'], true)
                        ? $mailer.' · not a real production mail transport'
                        : $mailer.' · real mail transport selected',
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Mail sender',
                    'meta' => (string) config('mail.from.address').' · '.(string) config('mail.from.name'),
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Queue driver',
                    'meta' => $queue === 'sync'
                        ? 'sync · mail sends during the web request'
                        : $queue.' · worker service must be running',
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Pending jobs',
                    'meta' => $pendingJobs.' queued job'.($pendingJobs === 1 ? '' : 's'),
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Ready jobs older than 5 minutes',
                    'meta' => $staleReadyJobs.' job'.($staleReadyJobs === 1 ? '' : 's').' waiting for a worker',
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Failed jobs',
                    'meta' => $failedJobs.' failed job'.($failedJobs === 1 ? '' : 's'),
                    'url' => route('admin.settings.edit'),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function calendarConnectivitySection(): array
    {
        $activeConnections = GoogleCalendarConnection::whereNull('disconnected_at')
            ->whereNotNull('refresh_token')
            ->count();
        $syncedEvents = GoogleCalendarEvent::where('status', 'synced')->count();
        $failedEvents = GoogleCalendarEvent::where('status', 'failed')->count();
        $oauthConfigured = filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
        $redirectUrl = $this->googleCalendarRedirectUrl();
        $issueCount = ($oauthConfigured ? 0 : 1) + ($failedEvents > 0 ? 1 : 0);

        return [
            'key' => 'google-calendar-readiness',
            'title' => 'Google Calendar readiness',
            'description' => 'Direct Google Calendar sync only works for reviewers who connect their matching Google account; email .ics invites remain the fallback.',
            'severity' => $issueCount > 0 ? 'warning' : 'success',
            'count' => $issueCount,
            'action_label' => 'Open settings',
            'action_url' => route('admin.settings.edit'),
            'items' => [
                [
                    'title' => 'Google OAuth client',
                    'meta' => $oauthConfigured ? 'Configured' : 'Missing client id or secret',
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Calendar callback',
                    'meta' => $redirectUrl,
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Connected reviewer calendars',
                    'meta' => $activeConnections.' active connection'.($activeConnections === 1 ? '' : 's'),
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Synced calendar events',
                    'meta' => $syncedEvents.' event'.($syncedEvents === 1 ? '' : 's'),
                    'url' => route('admin.settings.edit'),
                ],
                [
                    'title' => 'Failed calendar syncs',
                    'meta' => $failedEvents.' failed event'.($failedEvents === 1 ? '' : 's'),
                    'url' => route('admin.settings.edit'),
                ],
            ],
        ];
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function staleReadyJobsCount(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->subMinutes(5)->timestamp)
            ->count();
    }

    private function googleCalendarRedirectUrl(): string
    {
        $redirect = (string) config('services.google.calendar_redirect');

        return str_starts_with($redirect, 'http') ? $redirect : url($redirect);
    }
}
