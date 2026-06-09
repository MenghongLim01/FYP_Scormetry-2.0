<?php

use App\Http\Controllers\Admin\ActingRoleController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\ClassroomControlController as AdminClassroomControlController;
use App\Http\Controllers\Admin\ClassroomController as AdminClassroomController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaperScoreController as AdminPaperScoreController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewScoreController as AdminReviewScoreController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssignedTeamsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefenseAttemptController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OtpChallengeController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Invitation acceptance — public route so email links work before login/register
Route::get('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

// Public legal pages — no login required (needed for Google OAuth verification)
Route::inertia('privacy', 'Privacy')->name('privacy.show');
Route::inertia('terms', 'Terms')->name('terms.show');

// Google OAuth
Route::get('auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback');

// Email-OTP login challenge — reachable while a session is pending OTP, before
// the verified/approved gates, so it never loops.
Route::middleware('auth')->group(function () {
    Route::get('otp-challenge', [OtpChallengeController::class, 'show'])->name('otp.challenge');
    Route::post('otp-challenge', [OtpChallengeController::class, 'verify'])->name('otp.verify');
    Route::post('otp-challenge/resend', [OtpChallengeController::class, 'resend'])->name('otp.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pending-approval', function (Request $request) {
        if ($request->user()->isApproved()) {
            return redirect()->route('dashboard');
        }

        return inertia('auth/PendingApproval');
    })->name('pending-approval');

    Route::middleware('approved')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Subjects — explicit named routes must come before the resource to avoid {subject} wildcard collisions
        Route::get('subjects/join', fn () => inertia('subjects/Join'))->name('subjects.join.form');
        Route::post('subjects/join', [SubjectController::class, 'join'])->name('subjects.join');
        Route::get('subjects/join-as-reviewer', fn () => inertia('subjects/JoinAsReviewer'))->name('subjects.join-as-reviewer.form');
        Route::post('subjects/join-as-reviewer', [SubjectController::class, 'joinAsReviewer'])->name('subjects.join-as-reviewer');
        Route::resource('subjects', SubjectController::class);
        Route::delete('subjects/{subject}/leave', [SubjectController::class, 'leave'])->name('subjects.leave');
        Route::post('subjects/{subject}/students', [SubjectController::class, 'addStudent'])->name('subjects.students.add');
        Route::delete('subjects/{subject}/students/{user}', [SubjectController::class, 'removeStudent'])->name('subjects.students.remove');
        Route::post('subjects/{subject}/reviewers', [SubjectController::class, 'addReviewer'])->name('subjects.reviewers.add');
        Route::delete('subjects/{subject}/reviewers/{user}', [SubjectController::class, 'removeReviewer'])->name('subjects.reviewers.remove');
        Route::patch('subjects/{subject}/join-code/reset', [SubjectController::class, 'resetJoinCode'])->name('subjects.join-code.reset');
        Route::patch('subjects/{subject}/reviewer-code/reset', [SubjectController::class, 'resetReviewerCode'])->name('subjects.reviewer-code.reset');
        Route::patch('subjects/{subject}/members/{user}/approve', [SubjectController::class, 'approveMember'])->name('subjects.members.approve');
        Route::patch('subjects/{subject}/members/{user}/reject', [SubjectController::class, 'rejectMember'])->name('subjects.members.reject');
        Route::post('subjects/{subject}/pin', [SubjectController::class, 'togglePin'])->name('subjects.pin');
        Route::post('subjects/{subject}/archive', [SubjectController::class, 'archive'])->name('subjects.archive');
        Route::post('subjects/{subject}/unarchive', [SubjectController::class, 'unarchive'])->name('subjects.unarchive');
        Route::post('subjects/reorder', [SubjectController::class, 'reorder'])->name('subjects.reorder');
        Route::post('subjects/{subject}/reviewers/approve-all', [SubjectController::class, 'approveAllReviewerRequests'])->name('subjects.reviewers.approve-all');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // Teams
        Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('assigned-teams', [AssignedTeamsController::class, 'index'])->name('teams.assigned');

        // Optional Google Calendar connection (judge workspace). Separate from
        // login — the calendar scope is only ever requested here.
        Route::get('google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('google-calendar.connect');
        Route::get('google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google-calendar.callback');
        Route::delete('google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google-calendar.disconnect');
        Route::post('subjects/{subject}/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::patch('teams/{team}/topic', [TeamController::class, 'updateTopic'])->name('teams.topic.update');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.add');
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.remove');
        Route::delete('teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');
        Route::post('teams/{team}/advisor', [TeamController::class, 'setAdvisor'])->name('teams.advisor.set');
        Route::delete('teams/{team}/advisor', [TeamController::class, 'removeAdvisor'])->name('teams.advisor.remove');
        Route::post('teams/{team}/advisor/request-removal', [TeamController::class, 'requestAdvisorRemoval'])->name('teams.advisor.request-removal');
        Route::post('teams/{team}/members/{user}/request-removal', [TeamController::class, 'requestMemberRemoval'])->name('teams.members.request-removal');
        Route::post('team-requests/{teamRequest}/approve', [TeamController::class, 'approveTeamRequest'])->name('team-requests.approve');
        Route::delete('team-requests/{teamRequest}/reject', [TeamController::class, 'rejectTeamRequest'])->name('team-requests.reject');
        Route::patch('teams/{team}/schedule', [TeamController::class, 'updateSchedule'])->name('teams.schedule.update');
        Route::get('teams/{team}/scores', [TeamController::class, 'scores'])->name('teams.scores');
        Route::post('teams/{team}/release-scores', [TeamController::class, 'releaseScores'])->name('teams.release-scores');
        Route::get('teams/{team}/result', [TeamController::class, 'result'])->name('teams.result');

        // Evaluation rounds
        Route::post('defense-periods/{defensePeriod}/attempts', [DefenseAttemptController::class, 'store'])->name('defense-periods.attempts.store');
        Route::patch('defense-attempts/{defenseAttempt}', [DefenseAttemptController::class, 'update'])->name('defense-attempts.update');
        Route::delete('defense-attempts/{defenseAttempt}', [DefenseAttemptController::class, 'destroy'])->name('defense-attempts.destroy');
        Route::post('defense-attempts/{defenseAttempt}/extend-upload', [DefenseAttemptController::class, 'extendUpload'])->name('defense-attempts.extend-upload');
        Route::post('defense-attempts/{defenseAttempt}/reviewers/request', [DefenseAttemptController::class, 'requestReviewer'])->name('defense-attempts.reviewers.request');
        Route::patch('defense-attempts/{defenseAttempt}/reviewers/{user}/approve', [DefenseAttemptController::class, 'approveReviewer'])->name('defense-attempts.reviewers.approve');
        Route::patch('defense-attempts/{defenseAttempt}/reviewers/{user}/reject', [DefenseAttemptController::class, 'rejectReviewer'])->name('defense-attempts.reviewers.reject');
        Route::post('defense-attempts/{defenseAttempt}/reviewers', [DefenseAttemptController::class, 'addReviewer'])->name('defense-attempts.reviewers.add');
        Route::patch('defense-attempts/{defenseAttempt}/reviewers/{user}/role', [DefenseAttemptController::class, 'updateReviewerRole'])->name('defense-attempts.reviewers.role');
        Route::delete('defense-attempts/{defenseAttempt}/reviewers/{user}', [DefenseAttemptController::class, 'removeReviewer'])->name('defense-attempts.reviewers.remove');
        Route::patch('defense-attempts/{defenseAttempt}/override-score', [DefenseAttemptController::class, 'overrideScore'])->name('defense-attempts.override-score');
        Route::post('defense-attempts/{defenseAttempt}/release-scores', [DefenseAttemptController::class, 'releaseScores'])->name('defense-attempts.release-scores');

        // Rubrics
        Route::get('subjects/{subject}/rubrics/create', [RubricController::class, 'create'])->name('rubrics.create');
        Route::post('subjects/{subject}/rubrics', [RubricController::class, 'store'])->name('rubrics.store');
        Route::get('rubrics/{rubric}', [RubricController::class, 'show'])->name('rubrics.show');
        Route::get('rubrics/{rubric}/pdf', [RubricController::class, 'servePdf'])->name('rubrics.pdf');
        Route::get('rubrics/{rubric}/edit', [RubricController::class, 'edit'])->name('rubrics.edit');
        Route::patch('rubrics/{rubric}', [RubricController::class, 'update'])->name('rubrics.update');
        Route::delete('rubrics/{rubric}', [RubricController::class, 'destroy'])->name('rubrics.destroy');
        Route::post('rubrics/{rubric}/approve', [RubricController::class, 'approve'])->name('rubrics.approve');

        // Papers
        Route::get('papers', [PaperController::class, 'index'])->name('papers.index');
        Route::get('subjects/{subject}/papers/create', [PaperController::class, 'create'])->name('papers.create');
        Route::post('papers', [PaperController::class, 'store'])->name('papers.store');
        Route::get('papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
        Route::get('papers/{paper}/pdf', [PaperController::class, 'servePdf'])->name('papers.pdf');
        Route::post('papers/{paper}/publish', [PaperController::class, 'publish'])->name('papers.publish');
        Route::post('papers/{paper}/turn-in', [PaperController::class, 'turnIn'])->name('papers.turn-in');
        Route::post('papers/{paper}/unsubmit', [PaperController::class, 'unsubmit'])->name('papers.unsubmit');
        Route::delete('papers/{paper}', [PaperController::class, 'removeSubmission'])->name('papers.remove');

        // Reviews
        Route::get('papers/{paper}/reviews/create', [ReviewController::class, 'create'])->name('reviews.create');
        Route::post('papers/{paper}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::get('reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::post('reviews/{review}/unlock', [ReviewController::class, 'unlock'])->name('reviews.unlock');

        // Admin
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('system-health', [AdminSystemHealthController::class, 'index'])->name('system-health.index');
            Route::get('classrooms', [AdminClassroomController::class, 'index'])->name('classrooms.index');
            Route::get('classrooms/{subject}/control', [AdminClassroomControlController::class, 'show'])->name('classrooms.control');
            Route::patch('classrooms/{subject}/owner', [AdminClassroomControlController::class, 'updateOwner'])->name('classrooms.owner.update');
            Route::post('classrooms/{subject}/members', [AdminClassroomControlController::class, 'addMember'])->name('classrooms.members.add');
            Route::delete('classrooms/{subject}/members/{user}', [AdminClassroomControlController::class, 'removeMember'])->name('classrooms.members.remove');
            Route::delete('classrooms/{subject}', [AdminClassroomController::class, 'destroy'])->name('classrooms.destroy');
            Route::patch('classrooms/{subject}/reset-code', [AdminClassroomController::class, 'resetJoinCode'])->name('classrooms.reset-join-code');
            Route::patch('classrooms/{subject}/reset-reviewer-code', [AdminClassroomController::class, 'resetReviewerCode'])->name('classrooms.reset-reviewer-code');
            Route::patch('papers/{paper}/override-score', [AdminPaperScoreController::class, 'update'])->name('papers.override-score');
            Route::patch('reviews/{review}/correct-score', [AdminReviewScoreController::class, 'update'])->name('reviews.correct-score');

            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
            Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');
            Route::patch('users/{user}/block', [AdminUserController::class, 'block'])->name('users.block');
            Route::patch('users/{user}/unblock', [AdminUserController::class, 'unblock'])->name('users.unblock');
            Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
            Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
            Route::patch('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
            Route::post('acting-role', [ActingRoleController::class, 'store'])->name('acting-role.store');
            Route::delete('acting-role', [ActingRoleController::class, 'destroy'])->name('acting-role.destroy');

            // Reports — CSV exports of scores / subjects / teams
            Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
            Route::get('reports/scores.csv', [AdminReportController::class, 'scoresCsv'])->name('reports.scores.csv');
            Route::get('reports/subjects.csv', [AdminReportController::class, 'subjectsCsv'])->name('reports.subjects.csv');
            Route::get('reports/teams.csv', [AdminReportController::class, 'teamsCsv'])->name('reports.teams.csv');

            // Audit logs — surface review_unlock_logs + rubric_change_logs
            Route::get('audit/reviews', [AdminAuditLogController::class, 'reviewUnlocks'])->name('audit.reviews');
            Route::get('audit/rubrics', [AdminAuditLogController::class, 'rubricChanges'])->name('audit.rubrics');
        });
    });
});

require __DIR__.'/settings.php';
