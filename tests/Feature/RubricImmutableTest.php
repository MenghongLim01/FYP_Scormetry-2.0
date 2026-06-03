<?php

use App\Models\Rubric;
use App\Models\Subject;
use App\Models\User;

it('refuses to edit a locked rubric', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->locked()->create();

    $this->actingAs($owner)
        ->patch("/rubrics/{$rubric->id}", [
            'structure_json' => [
                ['criteria' => 'Hacked', 'max_score' => 100, 'weight' => 100],
            ],
            'correction_reason' => 'trying to change after lock',
        ])
        ->assertSessionHasErrors('rubric');

    // structure unchanged
    expect($rubric->fresh()->structure_json[0]['criteria'])->not->toBe('Hacked');
});

it('redirects away from the edit page for a locked rubric', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->locked()->create();

    $this->actingAs($owner)
        ->get("/rubrics/{$rubric->id}/edit")
        ->assertRedirect("/rubrics/{$rubric->id}");
});

it('still allows editing an unlocked (pending) rubric', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->pending()->create();

    $this->actingAs($owner)
        ->patch("/rubrics/{$rubric->id}", [
            'structure_json' => [
                ['criteria' => 'Updated Criterion', 'max_score' => 50, 'weight' => 100],
            ],
            'correction_reason' => 'fixing a typo before lock',
        ])
        ->assertRedirect("/rubrics/{$rubric->id}");

    expect($rubric->fresh()->structure_json[0]['criteria'])->toBe('Updated Criterion');
});

it('lets a reviewer view but not edit the rubric', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $rubric = Rubric::factory()->for($subject)->locked()->create();

    // can view
    $this->actingAs($reviewer)->get("/rubrics/{$rubric->id}")->assertOk();
    // cannot open edit
    $this->actingAs($reviewer)->get("/rubrics/{$rubric->id}/edit")->assertForbidden();
});
