<?php

use App\Models\DefensePeriod;
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

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->get('/assigned-teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('teams/AssignedTeams')
            ->has('teams', 0)
            ->has('availableTeams', 1)
            ->where('availableTeams.0.name', 'Team Schedule')
            ->where('availableTeams.0.period.name', 'Midterm Defense')
            ->where('availableTeams.0.defense_room', 'Room 1')
            ->where('availableTeams.0.paper_status', 'available_after_approval')
            ->where('availableTeams.0.assignment_status', null),
        );

    $this->actingAs($reviewer)
        ->post("/defense-attempts/{$attempt->id}/reviewers/request")
        ->assertRedirect();

    $this->actingAs($reviewer)
        ->get('/assigned-teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('teams/AssignedTeams')
            ->has('availableTeams', 1)
            ->where('availableTeams.0.assignment_status', 'pending'),
        );
});

it('shows papers to a reviewer only from teams they are assigned to', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

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
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

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
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

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
        'committee_role' => 'adviser',
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

it('forbids a non-owner from assigning a reviewer to a team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team->members()->attach($student);

    // A student team member cannot assign a reviewer.
    $this->actingAs($student)
        ->post("/teams/{$team->id}/members", ['email' => $reviewer->email])
        ->assertForbidden();

    expect($team->fresh()->members->pluck('id'))->not->toContain($reviewer->id);
});

it('allows the subject owner to assign a reviewer to a team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();

    $this->actingAs($owner)
        ->post("/teams/{$team->id}/members", ['email' => $reviewer->email])
        ->assertRedirect();

    expect($team->fresh()->members->pluck('id'))->toContain($reviewer->id);
});

it('forbids a non-owner from unassigning a reviewer from a team', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

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
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser', 'status' => 'approved']);

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($reviewer);

    $this->actingAs($owner)
        ->delete("/teams/{$team->id}/members/{$reviewer->id}")
        ->assertRedirect();

    expect($team->fresh()->members->pluck('id'))->not->toContain($reviewer->id);
});
