<?php

use App\Http\Controllers\Admin\ActingRoleController;
use App\Http\Controllers\Admin\ClassroomController as AdminClassroomController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaperScoreController as AdminPaperScoreController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssignedTeamsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefenseAttemptController;
use App\Http\Controllers\InvitationController;
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

// Google OAuth
Route::get('auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback');

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

        // Teams
        Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('assigned-teams', [AssignedTeamsController::class, 'index'])->name('teams.assigned');
        Route::post('subjects/{subject}/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.add');
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.remove');
        Route::delete('teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');
        Route::patch('teams/{team}/schedule', [TeamController::class, 'updateSchedule'])->name('teams.schedule.update');
        Route::get('teams/{team}/scores', [TeamController::class, 'scores'])->name('teams.scores');
        Route::post('teams/{team}/release-scores', [TeamController::class, 'releaseScores'])->name('teams.release-scores');
        Route::get('teams/{team}/result', [TeamController::class, 'result'])->name('teams.result');

        // Evaluation rounds
        Route::post('defense-periods/{defensePeriod}/attempts', [DefenseAttemptController::class, 'store'])->name('defense-periods.attempts.store');
        Route::patch('defense-attempts/{defenseAttempt}', [DefenseAttemptController::class, 'update'])->name('defense-attempts.update');
        Route::delete('defense-attempts/{defenseAttempt}', [DefenseAttemptController::class, 'destroy'])->name('defense-attempts.destroy');
        Route::post('defense-attempts/{defenseAttempt}/reviewers/request', [DefenseAttemptController::class, 'requestReviewer'])->name('defense-attempts.reviewers.request');
        Route::patch('defense-attempts/{defenseAttempt}/reviewers/{user}/approve', [DefenseAttemptController::class, 'approveReviewer'])->name('defense-attempts.reviewers.approve');
        Route::patch('defense-attempts/{defenseAttempt}/reviewers/{user}/reject', [DefenseAttemptController::class, 'rejectReviewer'])->name('defense-attempts.reviewers.reject');
        Route::post('defense-attempts/{defenseAttempt}/reviewers', [DefenseAttemptController::class, 'addReviewer'])->name('defense-attempts.reviewers.add');
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

        // Reviews
        Route::get('papers/{paper}/reviews/create', [ReviewController::class, 'create'])->name('reviews.create');
        Route::post('papers/{paper}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::get('reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::post('reviews/{review}/unlock', [ReviewController::class, 'unlock'])->name('reviews.unlock');

        // Admin
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('classrooms', [AdminClassroomController::class, 'index'])->name('classrooms.index');
            Route::delete('classrooms/{subject}', [AdminClassroomController::class, 'destroy'])->name('classrooms.destroy');
            Route::patch('classrooms/{subject}/reset-code', [AdminClassroomController::class, 'resetJoinCode'])->name('classrooms.reset-join-code');
            Route::patch('papers/{paper}/override-score', [AdminPaperScoreController::class, 'update'])->name('papers.override-score');

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
        });
    });
});

require __DIR__.'/settings.php';
