<?php

use App\Models\Subject;
use App\Models\User;

it('lets a teacher pin and unpin their own subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    // Pin
    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/pin")
        ->assertRedirect();
    expect($teacher->pinnedSubjects()->whereKey($subject->id)->exists())->toBeTrue();

    // Unpin (toggle)
    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/pin")
        ->assertRedirect();
    expect($teacher->pinnedSubjects()->whereKey($subject->id)->exists())->toBeFalse();
});

it('surfaces is_pinned and sorts pinned subjects to the top on the index', function () {
    $teacher = User::factory()->teacher()->create();
    $a = Subject::factory()->for($teacher, 'teacher')->create(['title' => 'Alpha']);
    $b = Subject::factory()->for($teacher, 'teacher')->create(['title' => 'Bravo']);
    $c = Subject::factory()->for($teacher, 'teacher')->create(['title' => 'Charlie']);

    // Pin the middle-created one.
    $teacher->pinnedSubjects()->attach($b->id);

    $this->actingAs($teacher)
        ->get('/subjects')
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Index')
            ->where('subjects.0.id', $b->id)          // pinned one is first
            ->where('subjects.0.is_pinned', true)
        );
});

it('forbids pinning a subject the user cannot see', function () {
    $owner = User::factory()->teacher()->create();
    $outsider = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($outsider)
        ->post("/subjects/{$subject->id}/pin")
        ->assertForbidden();

    expect($outsider->pinnedSubjects()->whereKey($subject->id)->exists())->toBeFalse();
});

it('lets an enrolled student pin a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student->enrolledSubjects()->attach($subject, ['status' => 'approved']);

    $this->actingAs($student)
        ->post("/subjects/{$subject->id}/pin")
        ->assertRedirect();

    expect($student->pinnedSubjects()->whereKey($subject->id)->exists())->toBeTrue();
});
