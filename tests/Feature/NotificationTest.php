<?php

use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('creates an in-app notification for the teacher and reviewers when a paper is uploaded', function () {
    Mail::fake();
    Storage::fake('private');

    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);
    $student->enrolledSubjects()->attach($subject, ['status' => 'approved']);

    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1,
        'attempt_type' => 'regular', 'status' => 'scheduled',
    ]);
    $attempt->reviewerAssignments()->create([
        'reviewer_id' => $reviewer->id, 'committee_role' => 'guest_panel', 'status' => 'active', 'excluded_from_calculation' => false,
    ]);

    $this->actingAs($student)->post('/papers', [
        'team_id' => $team->id,
        'subject_id' => $subject->id,
        'defense_attempt_id' => $attempt->id,
        'file' => UploadedFile::fake()->create('p.pdf', 50, 'application/pdf'),
    ]);

    // Notifications fire on turn-in (not on attach).
    $paper = \App\Models\Paper::where('team_id', $team->id)->latest('id')->firstOrFail();
    $this->actingAs($student)->post("/papers/{$paper->id}/turn-in");

    expect($teacher->fresh()->notifications()->count())->toBe(1);
    expect($reviewer->fresh()->notifications()->count())->toBe(1);
    expect($teacher->fresh()->unreadNotifications()->count())->toBe(1);
});

it('marks a notification as read', function () {
    $user = User::factory()->teacher()->create();
    $user->notify(new GenericNotification('Hi', 'Body', '/papers', 'system'));
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)->post("/notifications/{$id}/read")->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function () {
    $user = User::factory()->teacher()->create();
    $user->notify(new GenericNotification('A', 'a', null, 'system'));
    $user->notify(new GenericNotification('B', 'b', null, 'system'));
    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('renders the notifications page', function () {
    $user = User::factory()->teacher()->create();
    $user->notify(new GenericNotification('Hello', 'World', null, 'system'));

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn ($page) => $page->component('notifications/Index')->has('notifications.data', 1)->has('actionItems'));
});

it('shows instructor action items for pending reviewer requests', function () {
    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'active',
    ]);

    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Defense Session 1',
        'attempt_number' => 1,
        'attempt_type' => 'regular',
        'status' => 'scheduled',
    ]);

    $attempt->reviewerAssignments()->create([
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'technical_examiner',
        'status' => 'pending',
        'excluded_from_calculation' => false,
    ]);

    $this->actingAs($teacher)->get('/notifications')
        ->assertInertia(fn ($page) => $page
            ->component('notifications/Index')
            ->where('actionItems', fn ($items): bool => collect($items)->contains(
                fn (array $item): bool => $item['title'] === 'Reviewer request pending'
                    && $item['category'] === 'reviewer'
                    && $item['status'] === 'Needs action'
            ))
        );
});

it('shows reviewer action items for submitted documents that still need scoring', function () {
    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'active',
    ]);

    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Defense Session 1',
        'attempt_number' => 1,
        'attempt_type' => 'regular',
        'status' => 'scheduled',
        'score_deadline_at' => now()->addDay(),
    ]);

    $attempt->reviewerAssignments()->create([
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'academic_examiner',
        'status' => 'active',
        'excluded_from_calculation' => false,
    ]);

    Paper::factory()->submitted()->for($team)->for($subject)->create([
        'defense_attempt_id' => $attempt->id,
    ]);

    $this->actingAs($reviewer)->get('/notifications')
        ->assertInertia(fn ($page) => $page
            ->component('notifications/Index')
            ->where('actionItems', fn ($items): bool => collect($items)->contains(
                fn (array $item): bool => $item['title'] === 'Score this defense'
                    && $item['category'] === 'review'
                    && $item['action_label'] === 'Open scoring'
            ))
        );
});
