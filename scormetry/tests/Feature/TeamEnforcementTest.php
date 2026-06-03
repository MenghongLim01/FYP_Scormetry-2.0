<?php

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

it('allows a reviewer/judge to be added to multiple teams in the same subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team1 = Team::factory()->for($subject)->create();
    $team2 = Team::factory()->for($subject)->create();
    $reviewer = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel']);

    $this->actingAs($teacher)
        ->post("/teams/{$team1->id}/members", ['email' => $reviewer->email])
        ->assertRedirect();

    $this->actingAs($teacher)
        ->post("/teams/{$team2->id}/members", ['email' => $reviewer->email])
        ->assertRedirect();

    expect($team1->fresh()->members)->toHaveCount(1);
    expect($team2->fresh()->members)->toHaveCount(1);
});

it('does not enrol a reviewer as a student when added to a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $reviewer = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser']);

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
