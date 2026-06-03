<?php

use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('blocks releasing results when no score has been calculated', function () {
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
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'scheduled',
    ]);
    // paper exists but no final_score
    Paper::factory()->for($subject)->for($team, 'team')->submitted()->create(['defense_attempt_id' => $attempt->id, 'final_score' => null]);

    $this->actingAs($owner)
        ->post("/teams/{$team->id}/release-scores")
        ->assertSessionHasErrors('release');

    expect($team->fresh()->results_released_at)->toBeNull();
});

it('keeps student results pending when release exists without a calculated score', function () {
    $owner = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular',
        'status' => 'published', 'results_released_at' => now(),
    ]);

    Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
        'final_score' => null,
        'visibility_status' => 'published',
    ]);

    $this->actingAs($student)
        ->get("/teams/{$team->id}/result")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('teams/ResultPending'));
});

it('approves all pending reviewer requests in one call', function () {
    $owner = User::factory()->teacher()->create();
    $r1 = User::factory()->teacher()->create();
    $r2 = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($r1, ['role' => 'guest_panel', 'status' => 'approved']);
    $subject->reviewers()->attach($r2, ['role' => 'advisor', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id, 'name' => 'Final', 'type' => 'final', 'sequence' => 2,
        'score_scale' => 'points_100', 'passing_score' => 50, 'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id, 'label' => 'Attempt 1', 'attempt_number' => 1, 'attempt_type' => 'regular', 'status' => 'scheduled',
    ]);
    foreach ([$r1, $r2] as $r) {
        DefenseAttemptReviewer::create([
            'defense_attempt_id' => $attempt->id, 'reviewer_id' => $r->id,
            'committee_role' => 'guest_panel', 'status' => 'pending', 'excluded_from_calculation' => false,
        ]);
    }

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers/approve-all")
        ->assertRedirect();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)->where('status', 'pending')->count())->toBe(0);
    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)->whereIn('reviewer_id', [$r1->id, $r2->id])->where('status', 'active')->count())->toBe(2);
    expect($team->fresh()->members()->whereIn('users.id', [$r1->id, $r2->id])->count())->toBe(2);
});

it('forbids a non-owner from bulk-approving reviewer requests', function () {
    $owner = User::factory()->teacher()->create();
    $outsider = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($outsider)
        ->post("/subjects/{$subject->id}/reviewers/approve-all")
        ->assertForbidden();
});

it('serves the About, Terms and Policy settings pages', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/settings/about')->assertOk();
    $this->actingAs($user)->get('/settings/terms')->assertOk();
    $this->actingAs($user)->get('/settings/policy')->assertOk();
});
