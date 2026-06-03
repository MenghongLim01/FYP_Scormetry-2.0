<?php

use App\Models\Subject;
use App\Models\User;

it('shows subjects index for authenticated users', function () {
    $teacher = User::factory()->teacher()->create();
    Subject::factory()->for($teacher, 'teacher')->create(['title' => 'Math 101']);

    $this->actingAs($teacher)
        ->get('/subjects')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Index')
            ->has('subjects', 1)
        );
});

it('allows teachers to create subjects', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post('/subjects', [
            'title' => 'Physics 201',
            'description' => 'Advanced physics',
            'passing_score' => 75,
        ])
        ->assertRedirect();

    $subject = Subject::where('title', 'Physics 201')->first();
    expect($subject)->not->toBeNull()
        ->and($subject->teacher_id)->toBe($teacher->id);
});

it('shows the create subject form', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get('/subjects/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('subjects/Create'));
});

it('shows a single subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->get("/subjects/{$subject->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Show')
            ->has('subject')
        );
});

it('allows teacher to update their subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->patch("/subjects/{$subject->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'passing_score' => 80,
        ])
        ->assertRedirect("/subjects/{$subject->id}");

    expect($subject->fresh()->title)->toBe('Updated Title');
});

it('allows teacher to delete their subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->delete("/subjects/{$subject->id}")
        ->assertRedirect('/subjects');

    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
});

it('allows adding students to a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/students", ['email' => $student->email])
        ->assertRedirect();

    expect($subject->students)->toHaveCount(1);
});

it('allows removing students from a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->students()->attach($student);

    $this->actingAs($teacher)
        ->delete("/subjects/{$subject->id}/students/{$student->id}")
        ->assertRedirect();

    expect($subject->fresh()->students)->toHaveCount(0);
});

it('prevents guests from accessing subjects', function () {
    $this->get('/subjects')->assertRedirect('/login');
});

it('allows adding a reviewer to a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => $reviewer->email,
            'committee_role' => 'advisor',
        ])
        ->assertRedirect();

    expect($subject->fresh()->reviewers)->toHaveCount(1)
        ->and($subject->fresh()->reviewers->first()->pivot->role)->toBe('advisor');
});

it('allows removing a reviewer from a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer->id, ['role' => 'guest_panel']);

    $this->actingAs($teacher)
        ->delete("/subjects/{$subject->id}/reviewers/{$reviewer->id}")
        ->assertRedirect();

    expect($subject->fresh()->reviewers)->toHaveCount(0);
});

it('returns flash success message when creating a subject', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post('/subjects', [
            'title' => 'Flash Test Subject',
            'passing_score' => 70,
        ])
        ->assertSessionHas('success');
});

it('returns flash success message when updating a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->patch("/subjects/{$subject->id}", [
            'title' => 'Updated Flash',
            'passing_score' => 80,
        ])
        ->assertSessionHas('success', 'Subject updated successfully.');
});

it('returns flash success message when deleting a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->delete("/subjects/{$subject->id}")
        ->assertSessionHas('success', 'Subject deleted successfully.');
});

it('allows admin to update any subject', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($admin)
        ->patch("/subjects/{$subject->id}", [
            'title' => 'Admin Updated',
            'passing_score' => 90,
        ])
        ->assertRedirect();

    expect($subject->fresh()->title)->toBe('Admin Updated');
});

it('includes student and paper counts on index page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);

    $this->actingAs($teacher)
        ->get('/subjects')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Index')
            ->has('subjects', 1)
            ->where('subjects.0.students_count', 1)
            ->where('subjects.0.papers_count', 0)
        );
});

it('includes stats prop on show page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->get("/subjects/{$subject->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Show')
            ->has('stats')
            ->where('stats.students', 0)
            ->where('stats.reviewers', 0)
            ->where('stats.papers', 0)
        );
});
