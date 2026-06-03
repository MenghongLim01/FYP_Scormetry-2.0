<?php

use App\Models\Paper;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('prevents non-owner from deleting a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($otherTeacher)
        ->delete("/subjects/{$subject->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
});

it('allows admin to delete any subject', function () {
    $admin = User::factory()->admin()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($admin)
        ->delete("/subjects/{$subject->id}")
        ->assertRedirect('/subjects');

    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
});

it('prevents non-owner from adding students', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($otherTeacher)
        ->post("/subjects/{$subject->id}/students", ['email' => $student->email])
        ->assertForbidden();
});

it('prevents non-owner from adding reviewers', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($otherTeacher)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => $reviewer->email,
            'committee_role' => 'advisor',
        ])
        ->assertForbidden();
});

it('prevents non-owner from publishing a paper', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $paper = Paper::factory()->create(['final_score' => 80]);

    $this->actingAs($otherTeacher)
        ->post("/papers/{$paper->id}/publish")
        ->assertForbidden();
});

it('prevents non-owner from deleting a team', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $team = Team::factory()->create();

    $this->actingAs($otherTeacher)
        ->delete("/teams/{$team->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('teams', ['id' => $team->id]);
});

it('prevents non-owner from adding team members', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $team = Team::factory()->create();

    $this->actingAs($otherTeacher)
        ->post("/teams/{$team->id}/members", ['email' => $student->email])
        ->assertForbidden();
});

it('prevents non-reviewer from accessing review form', function () {
    $student = User::factory()->student()->create();
    $paper = Paper::factory()->submitted()->create();

    $this->actingAs($student)
        ->get("/papers/{$paper->id}/reviews/create")
        ->assertForbidden();
});

it('prevents non-owner from approving a rubric', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $rubric = Rubric::factory()->pending()->create();

    $this->actingAs($otherTeacher)
        ->post("/rubrics/{$rubric->id}/approve")
        ->assertForbidden();
});

it('prevents student from creating papers for unrelated subjects', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($student)
        ->get("/subjects/{$subject->id}/papers/create")
        ->assertForbidden();
});

it('allows enrolled student to access paper creation', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);

    $this->actingAs($student)
        ->get("/subjects/{$subject->id}/papers/create")
        ->assertSuccessful();
});
