<?php

use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

function makeReviewableSetup(): array
{
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    // Reviewers are now assigned to a team through a defense session (Manage Judges),
    // not by adding them as a plain team member.
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

    test()->actingAs($owner)->post("/defense-attempts/{$attempt->id}/reviewers", [
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'advisor',
    ])->assertRedirect();

    Rubric::factory()->for($subject)->locked()->create(['defense_period_id' => $attempt->defense_period_id]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create(['defense_attempt_id' => $attempt->id]);

    return [$owner, $reviewer, $paper];
}

it('saves a partial draft without locking or scoring', function () {
    [$owner, $reviewer, $paper] = makeReviewableSetup();

    test()->actingAs($reviewer)
        ->post("/papers/{$paper->id}/reviews", [
            'submit_final' => false,
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 40],
                ['criteria' => 'Presentation Quality', 'score' => 0], // not scored yet
            ],
        ])
        ->assertRedirect(); // back(), not papers.show

    $review = Review::where('paper_id', $paper->id)->firstOrFail();
    expect($review->is_submitted)->toBeFalse();
    expect($review->locked_at)->toBeNull();
    expect($paper->fresh()->final_score)->toBeNull(); // no recalculation on draft
});

it('locks the review on a final submit', function () {
    [$owner, $reviewer, $paper] = makeReviewableSetup();

    test()->actingAs($reviewer)
        ->post("/papers/{$paper->id}/reviews", [
            'submit_final' => true,
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 40],
                ['criteria' => 'Presentation Quality', 'score' => 45],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $review = Review::where('paper_id', $paper->id)->firstOrFail();
    expect($review->is_submitted)->toBeTrue();
    expect($review->locked_at)->not->toBeNull();
});

it('lets a judge save a draft, then submit it final later', function () {
    [$owner, $reviewer, $paper] = makeReviewableSetup();

    // draft
    test()->actingAs($reviewer)->post("/papers/{$paper->id}/reviews", [
        'submit_final' => false,
        'scores_json' => [['criteria' => 'Content Quality', 'score' => 30], ['criteria' => 'Presentation Quality', 'score' => 0]],
    ]);
    expect(Review::where('paper_id', $paper->id)->value('is_submitted'))->toBeFalse();

    // final
    test()->actingAs($reviewer)->post("/papers/{$paper->id}/reviews", [
        'submit_final' => true,
        'scores_json' => [['criteria' => 'Content Quality', 'score' => 35], ['criteria' => 'Presentation Quality', 'score' => 40]],
    ])->assertRedirect("/papers/{$paper->id}");

    $review = Review::where('paper_id', $paper->id)->firstOrFail();
    expect($review->is_submitted)->toBeTrue();
    expect($review->locked_at)->not->toBeNull();
    expect(collect($review->scores_json)->firstWhere('criteria', 'Content Quality')['score'])->toBe(35);
});

it('refuses to re-save a locked review until unlocked', function () {
    [$owner, $reviewer, $paper] = makeReviewableSetup();

    test()->actingAs($reviewer)->post("/papers/{$paper->id}/reviews", [
        'submit_final' => true,
        'scores_json' => [['criteria' => 'Content Quality', 'score' => 40], ['criteria' => 'Presentation Quality', 'score' => 45]],
    ]);

    // trying to draft over a locked review -> 403
    test()->actingAs($reviewer)->post("/papers/{$paper->id}/reviews", [
        'submit_final' => false,
        'scores_json' => [['criteria' => 'Content Quality', 'score' => 10], ['criteria' => 'Presentation Quality', 'score' => 10]],
    ])->assertForbidden();
});
