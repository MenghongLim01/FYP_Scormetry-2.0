<?php

use App\Models\Paper;
use App\Models\Review;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('prevents unauthorized users from viewing a subject', function () {
    $outsider = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($outsider)
        ->get("/subjects/{$subject->id}")
        ->assertForbidden();
});

it('allows subject teacher to view subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->get("/subjects/{$subject->id}")
        ->assertSuccessful();
});

it('prevents unauthorized users from viewing a paper', function () {
    $outsider = User::factory()->teacher()->create();
    $paper = Paper::factory()->submitted()->create();

    $this->actingAs($outsider)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();
});

it('allows team members to view their paper', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($student)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful();
});

it('prevents unauthorized users from viewing a review', function () {
    $outsider = User::factory()->student()->create();
    $review = Review::factory()->create(['is_submitted' => true]);

    $this->actingAs($outsider)
        ->get("/reviews/{$review->id}")
        ->assertForbidden();
});

it('allows review author to view their review', function () {
    $reviewer = User::factory()->teacher()->create();
    $review = Review::factory()->for($reviewer, 'reviewer')->create(['is_submitted' => true]);

    $this->actingAs($reviewer)
        ->get("/reviews/{$review->id}")
        ->assertSuccessful();
});

it('prevents unauthorized teacher from editing a subject', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($otherTeacher)
        ->get("/subjects/{$subject->id}/edit")
        ->assertForbidden();
});

it('redirects approved users away from pending-approval page', function () {
    $approvedUser = User::factory()->student()->create(['status' => 'approved']);

    $this->actingAs($approvedUser)
        ->get('/pending-approval')
        ->assertRedirect('/dashboard');
});
