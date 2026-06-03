<?php

use App\Models\DefensePeriod;
use App\Models\DefenseAttemptReviewer;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

it('hides papers from a reviewer when they are not assigned to the team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get('/papers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Index')
            ->has('papers', 0),
        );
});

it('shows scheduled teams to approved reviewers before document access is approved', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $subject->students()->attach($student, ['status' => 'approved']);
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $team = Team::factory()->for($subject)->create(['name' => 'Team Schedule']);
    $team->members()->attach($student);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'defense_date' => now()->addWeek()->format('Y-m-d'),
        'defense_time' => '09:30',
        'defense_duration' => 60,
        'defense_room' => 'Room 1',
        'status' => 'scheduled',
    ]);
    $paper = Paper::factory()
        ->for($subject)
        ->for($team, 'team')
        ->submitted()
        ->create(['defense_attempt_id' => $attempt->id]);

    // Before being assigned, the judge can neither open the document nor see the
    // team in their workspace (the standalone sign-up board has been removed).
    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->get('/assigned-teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('teams/AssignedTeams')
            ->has('teams', 0)
            ->missing('availableTeams'),
        );

    // The self-request flow still works: request -> pending, then owner approval -> active.
    $this->actingAs($reviewer)
        ->post("/defense-attempts/{$attempt->id}/reviewers/request")
        ->assertRedirect();

    $this->assertDatabaseHas('defense_attempt_reviewers', [
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}/approve")
        ->assertRedirect();

    $this->assertDatabaseHas('defense_attempt_reviewers', [
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'active',
    ]);

    // Once approved, the team shows up in the judge's workspace and the document opens.
    $this->actingAs($reviewer)
        ->get('/assigned-teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('teams/AssignedTeams')
            ->has('teams', 1)
            ->where('teams.0.name', 'Team Schedule'),
        );

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful();
});

it('lets the instructor directly assign an approved reviewer to one defense round only', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create(['name' => 'Team Round']);

    $midterm = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $final = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);

    $midtermAttempt = $team->defenseAttempts()->create([
        'defense_period_id' => $midterm->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);
    $finalAttempt = $team->defenseAttempts()->create([
        'defense_period_id' => $final->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);

    $this->actingAs($owner)
        ->post("/defense-attempts/{$finalAttempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'technical_examiner',
        ])
        ->assertRedirect();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $finalAttempt->id)
        ->where('reviewer_id', $reviewer->id)
        ->where('committee_role', 'Technical examiner')
        ->where('status', 'active')
        ->exists())->toBeTrue()
        ->and(DefenseAttemptReviewer::where('defense_attempt_id', $midtermAttempt->id)
            ->where('reviewer_id', $reviewer->id)
            ->exists())->toBeFalse()
        ->and($team->members()->whereKey($reviewer->id)->exists())->toBeTrue();
});

it('lets the instructor set and update a judge role for one defense round', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create(['name' => 'Team Roles']);

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);

    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);

    $this->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'academic_examiner',
        ])
        ->assertRedirect();

    $assignment = DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $reviewer->id)
        ->firstOrFail();

    expect($assignment->committee_role)->toBe('Academic examiner');

    $this->actingAs($owner)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}/role", [
            'committee_role' => 'custom',
            'role_label' => 'Industry examiner',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->committee_role)->toBe('Industry examiner');
});

it('shows papers to a reviewer only from teams they are assigned to', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $assignedTeam = Team::factory()->for($subject)->create();
    $assignedTeam->members()->attach($reviewer);
    Paper::factory()->for($subject)->for($assignedTeam, 'team')->submitted()->create();

    $unassignedTeam = Team::factory()->for($subject)->create();
    Paper::factory()->for($subject)->for($unassignedTeam, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get('/papers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Index')
            ->has('papers', 1),
        );
});

it('forbids a reviewer from viewing a paper from an unassigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();
});

it('allows a reviewer to view a paper from their assigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful();
});

it('forbids a reviewer from accessing the review create form for an unassigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'fyp_instructor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}/reviews/create")
        ->assertForbidden();
});

it('forbids a reviewer from submitting a review for an unassigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content', 'score' => 3],
            ],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('reviews', [
        'paper_id' => $paper->id,
        'reviewer_id' => $reviewer->id,
    ]);
});

it('allows a reviewer to submit a review for their assigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content', 'score' => 4],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $this->assertDatabaseHas('reviews', [
        'paper_id' => $paper->id,
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'advisor',
        'is_submitted' => true,
    ]);
});

it('forbids reviewer from accessing a review of an unassigned team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();
    $review = Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($reviewer)
        ->get("/reviews/{$review->id}")
        ->assertForbidden();
});

it('forbids paper pdf access for a reviewer not assigned to the team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    Storage::disk('private')->put('papers/x.pdf', 'fake');
    $paper = Paper::factory()->for($subject)->for($team, 'team')->create(['file_path' => 'papers/x.pdf']);

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}/pdf")
        ->assertForbidden();
});

it('filters subject papers shown to a reviewer to their assigned teams only', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'fyp_instructor', 'status' => 'approved']);

    $assignedTeam = Team::factory()->for($subject)->create();
    $assignedTeam->members()->attach($reviewer);
    Paper::factory()->for($subject)->for($assignedTeam, 'team')->submitted()->create();

    $unassignedTeam = Team::factory()->for($subject)->create();
    Paper::factory()->for($subject)->for($unassignedTeam, 'team')->submitted()->create();

    $this->actingAs($reviewer)
        ->get("/subjects/{$subject->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subjects/Show')
            ->has('subject.papers', 1),
        );
});

it('still allows the subject owner to see and review all papers', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();

    $team1 = Team::factory()->for($subject)->create();
    $team2 = Team::factory()->for($subject)->create();
    Paper::factory()->for($subject)->for($team1, 'team')->submitted()->create();
    $paper2 = Paper::factory()->for($subject)->for($team2, 'team')->submitted()->create();

    $this->actingAs($owner)
        ->get('/papers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Index')
            ->has('papers', 2),
        );

    $this->actingAs($owner)
        ->post("/papers/{$paper2->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content', 'score' => 3],
            ],
        ])
        ->assertRedirect("/papers/{$paper2->id}");
});

it('forbids a non-owner from assigning a reviewer to a defense round', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);
    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team->members()->attach($student);

    $this->actingAs($student)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'technical_examiner',
        ])
        ->assertForbidden();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $reviewer->id)
        ->exists())->toBeFalse();
});

it('allows the subject owner to assign a reviewer to a defense round', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);

    $this->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'academic_examiner',
        ])
        ->assertRedirect();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $reviewer->id)
        ->where('committee_role', 'Academic examiner')
        ->where('status', 'active')
        ->exists())->toBeTrue()
        ->and($team->fresh()->members->pluck('id'))->toContain($reviewer->id);
});

it('forbids a non-owner from unassigning a reviewer from a team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);

    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team->members()->attach($student);

    // A team-mate student cannot remove the reviewer.
    $this->actingAs($student)
        ->delete("/teams/{$team->id}/members/{$reviewer->id}")
        ->assertForbidden();

    // The reviewer cannot self-remove either.
    $this->actingAs($reviewer)
        ->delete("/teams/{$team->id}/members/{$reviewer->id}")
        ->assertForbidden();

    // The reviewer cannot leave the team to bypass the restriction.
    $this->actingAs($reviewer)
        ->delete("/teams/{$team->id}/leave")
        ->assertForbidden();

    expect($team->fresh()->members->pluck('id'))->toContain($reviewer->id);
});

it('allows the subject owner to unassign a reviewer from a team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);

    $this->actingAs($owner)
        ->delete("/teams/{$team->id}/members/{$reviewer->id}")
        ->assertRedirect();

    expect($team->fresh()->members->pluck('id'))->not->toContain($reviewer->id);
});

it('does not allow a judge to be removed from a team after they submitted feedback', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);

    $period = \App\Models\DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm',
        'type' => 'midterm',
        'sequence' => 1,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $attempt = \App\Models\DefenseAttempt::create([
        'defense_period_id' => $period->id,
        'team_id' => $team->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'attempt_type' => 'regular',
        'status' => 'scheduled',
    ]);
    $assignment = \App\Models\DefenseAttemptReviewer::create([
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'advisor',
        'status' => 'active',
        'excluded_from_calculation' => false,
    ]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);
    $review = Review::factory()->create([
        'paper_id' => $paper->id,
        'reviewer_id' => $reviewer->id,
        'defense_attempt_id' => $attempt->id,
        'defense_attempt_reviewer_id' => $assignment->id,
        'is_submitted' => true,
        'locked_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete("/teams/{$team->id}/members/{$reviewer->id}")
        ->assertSessionHasErrors('reviewer');

    // The reviewer stays attached to the team history because the defense work is complete.
    expect($team->fresh()->members->pluck('id'))->toContain($reviewer->id);

    // The submitted review (and its feedback) is NOT deleted.
    $review->refresh();
    expect(Review::find($review->id))->not->toBeNull();
    expect($review->is_submitted)->toBeTrue();

    // The submitted review remains linked to the active historical assignment.
    expect($review->defense_attempt_reviewer_id)->toBe($assignment->id);

    // The assignment remains active for the completed round.
    $this->assertDatabaseHas('defense_attempt_reviewers', [
        'id' => $assignment->id,
        'status' => 'active',
        'excluded_from_calculation' => false,
    ]);
});
