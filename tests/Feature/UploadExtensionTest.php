<?php

use App\Models\DefensePeriod;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('lets the owner reopen the upload window and notifies students', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);

    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1,
        'attempt_type' => 'regular', 'status' => 'scheduled',
        'paper_upload_deadline_at' => now()->subDay(), // already passed
    ]);

    expect($attempt->isPaperUploadOpen())->toBeFalse();

    $this->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/extend-upload", ['hours' => 24])
        ->assertRedirect();

    $attempt->refresh();
    expect($attempt->paper_upload_unlocked_until)->not->toBeNull();
    expect($attempt->isPaperUploadOpen())->toBeTrue();
    expect($student->fresh()->notifications()->count())->toBe(1);
});

it('rejects an invalid extension duration', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'setup',
    ]);

    $this->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/extend-upload", ['hours' => 999])
        ->assertSessionHasErrors('hours');
});

it('forbids a non-owner from extending the upload', function () {
    $owner = User::factory()->teacher()->create();
    $outsider = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'setup',
    ]);

    $this->actingAs($outsider)
        ->post("/defense-attempts/{$attempt->id}/extend-upload", ['hours' => 24])
        ->assertForbidden();
});
