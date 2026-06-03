<?php

use App\Models\DefensePeriod;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('prevents a student from joining a second team in the same subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team1 = Team::factory()->for($subject)->create();
    $team2 = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();

    $team1->members()->attach($student);
    $subject->students()->attach($student);

    $this->actingAs($teacher)
        ->post("/teams/{$team2->id}/members", ['email' => $student->email])
        ->assertSessionHasErrors('email');

    expect($team2->fresh()->members)->toHaveCount(0);
});

it('allows a student to join their first team in a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => $student->email])
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(1);
});

it('allows a reviewer/judge to be assigned to multiple teams in the same subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'active',
    ]);
    $team1 = Team::factory()->for($subject)->create();
    $team2 = Team::factory()->for($subject)->create();
    $attempt1 = $team1->defenseAttempts()->create(['defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'setup']);
    $attempt2 = $team2->defenseAttempts()->create(['defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'setup']);
    $reviewer = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    // Reviewers are assigned per defense session (Manage Judges), and — unlike students,
    // who are limited to one team — a judge may sit on multiple teams' sessions.
    $this->actingAs($teacher)
        ->post("/defense-attempts/{$attempt1->id}/reviewers", ['reviewer_id' => $reviewer->id, 'committee_role' => 'advisor'])
        ->assertRedirect();

    $this->actingAs($teacher)
        ->post("/defense-attempts/{$attempt2->id}/reviewers", ['reviewer_id' => $reviewer->id, 'committee_role' => 'advisor'])
        ->assertRedirect();

    // The judge ends up on both teams. (Each team also includes the subject owner,
    // who is auto-added as a reviewer when the defense attempt is created.)
    expect($team1->fresh()->members->pluck('id'))->toContain($reviewer->id);
    expect($team2->fresh()->members->pluck('id'))->toContain($reviewer->id);
});

it('does not enrol a reviewer as a student when added to a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $reviewer = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => $reviewer->email])
        ->assertRedirect();

    expect($subject->fresh()->students)->toHaveCount(0);
});

it('allows a member to leave their team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);

    $this->actingAs($student)
        ->delete("/teams/{$team->id}/leave")
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(0);
});
