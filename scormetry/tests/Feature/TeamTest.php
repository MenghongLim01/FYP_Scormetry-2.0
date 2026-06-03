<?php

use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('allows teacher to create a team for a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Alpha'])
        ->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'subject_id' => $subject->id,
        'name' => 'Team Alpha',
    ]);
});

it('allows teacher to delete a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->delete("/teams/{$team->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

it('allows adding members to a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => $student->email])
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(1);
});

it('allows removing members from a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);

    $this->actingAs($teacher)
        ->delete("/teams/{$team->id}/members/{$student->id}")
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(0);
});

it('validates team name is required', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('validates member email exists', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => 'nonexistent@example.com'])
        ->assertSessionHasErrors('email');
});
