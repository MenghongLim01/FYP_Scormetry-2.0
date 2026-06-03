<?php

use App\Models\Subject;
use App\Models\User;

it('lets the owner archive and restore a subject', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)->post("/subjects/{$subject->id}/archive")->assertRedirect();
    expect($subject->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($owner)->post("/subjects/{$subject->id}/unarchive")->assertRedirect();
    expect($subject->fresh()->archived_at)->toBeNull();
});

it('hides archived subjects from the active list and shows them under the archived view', function () {
    $owner = User::factory()->teacher()->create();
    $active = Subject::factory()->for($owner, 'teacher')->create(['title' => 'Active One']);
    $archived = Subject::factory()->for($owner, 'teacher')->create(['title' => 'Archived One', 'archived_at' => now()]);

    // active view
    $this->actingAs($owner)->get('/subjects')->assertInertia(fn ($p) => $p
        ->component('subjects/Index')
        ->where('archivedCount', 1)
        ->has('subjects', 1)
        ->where('subjects.0.id', $active->id),
    );

    // archived view
    $this->actingAs($owner)->get('/subjects?archived=1')->assertInertia(fn ($p) => $p
        ->where('showingArchived', true)
        ->has('subjects', 1)
        ->where('subjects.0.id', $archived->id),
    );
});

it('forbids a non-owner from archiving a subject', function () {
    $owner = User::factory()->teacher()->create();
    $outsider = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($outsider)->post("/subjects/{$subject->id}/archive")->assertForbidden();
});

it('saves and applies a per-user custom subject order', function () {
    $owner = User::factory()->teacher()->create();
    $a = Subject::factory()->for($owner, 'teacher')->create(['title' => 'A']);
    $b = Subject::factory()->for($owner, 'teacher')->create(['title' => 'B']);
    $c = Subject::factory()->for($owner, 'teacher')->create(['title' => 'C']);

    // user wants order C, A, B
    $this->actingAs($owner)->post('/subjects/reorder', ['order' => [$c->id, $a->id, $b->id]])->assertRedirect();

    $this->actingAs($owner)->get('/subjects')->assertInertia(fn ($p) => $p
        ->where('subjects.0.id', $c->id)
        ->where('subjects.1.id', $a->id)
        ->where('subjects.2.id', $b->id),
    );
});
