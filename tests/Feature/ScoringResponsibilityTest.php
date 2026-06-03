<?php

use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use App\Services\ReviewScoringService;

/**
 * Build a defense session with a submitted paper and a locked rubric. The reviewer
 * is an approved subject member (stored with the legacy guest_panel role on purpose,
 * to exercise backward-compatible display).
 *
 * @return array{0: User, 1: User, 2: Subject, 3: Team, 4: \App\Models\DefenseAttempt, 5: Paper}
 */
function responsibilitySetup(): array
{
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'active',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'attempt_type' => 'regular',
        'status' => 'scheduled',
    ]);
    Rubric::factory()->for($subject)->locked()->create(['defense_period_id' => $period->id]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create(['defense_attempt_id' => $attempt->id]);

    return [$owner, $reviewer, $subject, $team, $attempt, $paper];
}

function assignRole(User $owner, int $attemptId, int $reviewerId, string $role): void
{
    test()->actingAs($owner)->post("/defense-attempts/{$attemptId}/reviewers", [
        'reviewer_id' => $reviewerId,
        'committee_role' => $role,
    ]);
}

function submitResponsibility(User $reviewer, int $paperId, int $assignmentId, int $a, int $b): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($reviewer)->post("/papers/{$paperId}/reviews", [
        'submit_final' => true,
        'defense_attempt_reviewer_id' => $assignmentId,
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => $a, 'max_score' => 50, 'weight' => 50],
            ['criteria' => 'Presentation Quality', 'score' => $b, 'max_score' => 50, 'weight' => 50],
        ],
    ]);
}

it('lets the owner assign one reviewer two different scoring roles in the same session', function () {
    [$owner, $reviewer, , , $attempt] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewer->id, 'academic_examiner');

    $roles = $attempt->reviewerAssignments()
        ->where('reviewer_id', $reviewer->id)
        ->where('status', 'active')
        ->pluck('committee_role');

    expect($roles)->toHaveCount(2)
        ->and($roles)->toContain('Technical examiner')
        ->and($roles)->toContain('Academic examiner');
});

it('rejects assigning the same scoring role to a reviewer twice in one session', function () {
    [$owner, $reviewer, , , $attempt] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');

    test()->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'technical_examiner',
        ])
        ->assertSessionHasErrors('reviewer_id');

    expect($attempt->reviewerAssignments()
        ->where('reviewer_id', $reviewer->id)
        ->where('committee_role', 'Technical examiner')
        ->where('status', 'active')
        ->count())->toBe(1);
});

it('records separate reviews for each scoring role held by the same reviewer', function () {
    [$owner, $reviewer, , , $attempt, $paper] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewer->id, 'academic_examiner');

    $tech = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Technical examiner')->firstOrFail();
    $acad = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Academic examiner')->firstOrFail();

    submitResponsibility($reviewer, $paper->id, $tech->id, 50, 50)->assertRedirect("/papers/{$paper->id}");
    submitResponsibility($reviewer, $paper->id, $acad->id, 40, 40)->assertRedirect("/papers/{$paper->id}");

    $reviews = Review::where('paper_id', $paper->id)->where('reviewer_id', $reviewer->id)->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews->pluck('defense_attempt_reviewer_id')->unique())->toHaveCount(2);
});

it('averages the final score across scoring responsibilities, not unique users', function () {
    [$owner, $reviewer, , , $attempt, $paper] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewer->id, 'academic_examiner');

    $tech = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Technical examiner')->firstOrFail();
    $acad = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Academic examiner')->firstOrFail();

    // Technical examiner = 100%, Academic examiner = 80%. Two responsibilities by the
    // same user must each count, so the average is 90 — not 100 (which a per-user
    // average of a single merged review would produce).
    submitResponsibility($reviewer, $paper->id, $tech->id, 50, 50);
    submitResponsibility($reviewer, $paper->id, $acad->id, 40, 40);

    expect(round((float) $paper->fresh()->final_score, 2))->toBe(90.0);
});

it('is not ready to release until every active scoring responsibility is submitted', function () {
    [$owner, $reviewer, , , $attempt, $paper] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewer->id, 'academic_examiner');

    $tech = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Technical examiner')->firstOrFail();
    $acad = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Academic examiner')->firstOrFail();
    $ownerResp = $attempt->reviewerAssignments()->where('reviewer_id', $owner->id)->firstOrFail();

    $service = app(ReviewScoringService::class);

    // Owner (FYP Instructor) + Technical + Academic = 3 required responsibilities.
    submitResponsibility($reviewer, $paper->id, $tech->id, 50, 50);
    $summary = $service->responsibilitySummary($attempt->fresh(), $paper->fresh());
    expect($summary['required'])->toBe(3)
        ->and($summary['submitted'])->toBe(1)
        ->and($summary['ready'])->toBeFalse()
        ->and($summary['missing_roles'])->toContain('Academic examiner');

    submitResponsibility($reviewer, $paper->id, $acad->id, 40, 40);
    submitResponsibility($owner, $paper->id, $ownerResp->id, 45, 45);

    $summary = $service->responsibilitySummary($attempt->fresh(), $paper->fresh());
    expect($summary['submitted'])->toBe(3)
        ->and($summary['ready'])->toBeTrue()
        ->and($summary['missing_roles'])->toBe([]);
});

it('hides other judges feedback from a scoring judge', function () {
    [$owner, $reviewerA, $subject, , $attempt, $paper] = responsibilitySetup();
    $reviewerB = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewerB, ['role' => 'guest_panel', 'status' => 'approved']);

    assignRole($owner, $attempt->id, $reviewerA->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewerB->id, 'academic_examiner');

    $techA = $attempt->reviewerAssignments()->where('reviewer_id', $reviewerA->id)->firstOrFail();
    $acadB = $attempt->reviewerAssignments()->where('reviewer_id', $reviewerB->id)->firstOrFail();

    submitResponsibility($reviewerA, $paper->id, $techA->id, 50, 50);

    test()->actingAs($reviewerB)
        ->get("/papers/{$paper->id}/reviews/create?assignment={$acadB->id}")
        ->assertInertia(fn ($page) => $page->where('paper.reviews', []));
});

it('does not allow assigning fyp_instructor as a normal scoring role', function () {
    [$owner, $reviewer, , , $attempt] = responsibilitySetup();

    test()->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'fyp_instructor',
        ])
        ->assertSessionHasErrors('committee_role');
});

it('displays legacy guest_panel members safely as Custom role', function () {
    [$owner, $reviewer, , , $attempt, $paper] = responsibilitySetup();

    // Assign so the reviewer becomes a team member surfaced in the paper review panel.
    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');

    test()->actingAs($owner)
        ->get("/papers/{$paper->id}")
        ->assertInertia(fn ($page) => $page->where(
            'paper.team.review_panel',
            fn ($panel) => collect($panel)->contains(fn ($member) => $member['role_label'] === 'Custom role'),
        ));
});

it('blocks removing a scoring role that already has a submitted score', function () {
    [$owner, $reviewer, , , $attempt, $paper] = responsibilitySetup();

    assignRole($owner, $attempt->id, $reviewer->id, 'technical_examiner');
    assignRole($owner, $attempt->id, $reviewer->id, 'academic_examiner');

    $tech = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Technical examiner')->firstOrFail();
    $acad = $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->where('committee_role', 'Academic examiner')->firstOrFail();

    submitResponsibility($reviewer, $paper->id, $tech->id, 50, 50);

    // The submitted scoring role is an academic record — removal is rejected.
    test()->actingAs($owner)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}", ['assignment_id' => $tech->id])
        ->assertSessionHasErrors('reviewer');

    expect($attempt->reviewerAssignments()->whereKey($tech->id)->where('status', 'active')->exists())->toBeTrue();

    // The other (unscored) role can still be removed without touching the submitted one.
    test()->actingAs($owner)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}", ['assignment_id' => $acad->id])
        ->assertRedirect();

    expect($attempt->reviewerAssignments()->whereKey($acad->id)->exists())->toBeFalse();
    expect($attempt->reviewerAssignments()->whereKey($tech->id)->where('status', 'active')->exists())->toBeTrue();
});
