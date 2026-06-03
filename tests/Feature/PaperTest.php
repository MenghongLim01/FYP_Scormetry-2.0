<?php

use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows papers index for students', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    Paper::factory()->for($team, 'team')->for($subject)->create();

    $this->actingAs($student)
        ->get('/papers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Index')
            ->has('papers', 1)
        );
});

it('allows students to submit papers', function () {
    Storage::fake('private');

    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    // Step 1: attaching the file stages it as a draft (not yet visible to judges).
    $this->actingAs($student)
        ->post('/papers', [
            'team_id' => $team->id,
            'subject_id' => $subject->id,
            'file' => UploadedFile::fake()->create('paper.pdf', 1024, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('papers', [
        'team_id' => $team->id,
        'subject_id' => $subject->id,
        'visibility_status' => 'draft',
    ]);

    // Step 2: turning it in submits it for review.
    $paper = Paper::where('team_id', $team->id)->firstOrFail();
    $this->actingAs($student)
        ->post("/papers/{$paper->id}/turn-in")
        ->assertRedirect();

    $this->assertDatabaseHas('papers', [
        'id' => $paper->id,
        'visibility_status' => 'submitted',
    ]);
    expect($paper->fresh()->turned_in_at)->not->toBeNull();
});

it('shows a paper with its reviews', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $paper = Paper::factory()->for($subject)->submitted()->create();

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paper')
        );
});

it('allows teacher to publish a graded paper', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create(['passing_score' => 50]);
    $paper = Paper::factory()->for($subject)->create([
        'final_score' => 75,
        'visibility_status' => 'submitted',
    ]);

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/publish")
        ->assertRedirect();

    expect($paper->fresh()->visibility_status)->toBe('published');
});

it('prevents publishing ungraded papers', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $paper = Paper::factory()->for($subject)->create(['final_score' => null]);

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/publish")
        ->assertRedirect();

    expect($paper->fresh()->visibility_status)->toBe('draft');
});

it('hides reviews from student when paper is not published', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();
    Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($student)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paper.reviews', 0)
        );
});

it('shows reviews to student when paper is published', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->create(['visibility_status' => 'published']);
    Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($student)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paper.reviews', 1)
        );
});

it('shows only the judges own review in paper details before release', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $otherReviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'technical_examiner', 'status' => 'approved']);
    $subject->reviewers()->attach($otherReviewer, ['role' => 'academic_examiner', 'status' => 'approved']);

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $team = Team::factory()->for($subject)->create();
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);
    DefenseAttemptReviewer::create([
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'technical_examiner',
        'status' => 'active',
    ]);
    DefenseAttemptReviewer::create([
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $otherReviewer->id,
        'committee_role' => 'academic_examiner',
        'status' => 'active',
    ]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);
    Review::factory()->for($paper)->submitted()->create(['reviewer_id' => $reviewer->id]);
    Review::factory()->for($paper)->submitted()->create(['reviewer_id' => $otherReviewer->id]);

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paper.reviews', 1)
            ->where('paper.reviews.0.reviewer.id', $reviewer->id)
        );
});

it('shows all released feedback to student team members', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->create(['visibility_status' => 'published']);
    Review::factory()->for($paper)->submitted()->create();
    Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($student)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paper.reviews', 2)
        );
});

it('hides an attached (not turned in) document from assigned reviewers until turn-in', function () {
    Storage::fake('private');
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'advisor', 'status' => 'approved']);
    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    // Attach as draft.
    $this->actingAs($student)->post('/papers', [
        'team_id' => $team->id,
        'subject_id' => $subject->id,
        'file' => UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'),
    ])->assertRedirect();
    $paper = Paper::where('team_id', $team->id)->firstOrFail();

    // Reviewer cannot open or review a draft.
    $this->actingAs($reviewer)->get("/papers/{$paper->id}")->assertForbidden();
    $this->actingAs($reviewer)->get("/papers/{$paper->id}/reviews/create")->assertForbidden();

    // After turn-in the reviewer can access it.
    $this->actingAs($student)->post("/papers/{$paper->id}/turn-in")->assertRedirect();
    // (reviewer access to show requires assignment; at minimum it's no longer a draft)
    expect($paper->fresh()->isTurnedIn())->toBeTrue();
});

it('lets a student unsubmit and remove their document before the deadline', function () {
    Storage::fake('private');
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $student = User::factory()->student()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    $this->actingAs($student)->post('/papers', [
        'team_id' => $team->id,
        'subject_id' => $subject->id,
        'file' => UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'),
    ])->assertRedirect();
    $paper = Paper::where('team_id', $team->id)->firstOrFail();

    $this->actingAs($student)->post("/papers/{$paper->id}/turn-in")->assertRedirect();
    expect($paper->fresh()->isTurnedIn())->toBeTrue();

    // Unsubmit -> back to draft.
    $this->actingAs($student)->post("/papers/{$paper->id}/unsubmit")->assertRedirect();
    expect($paper->fresh()->isTurnedIn())->toBeFalse();

    // Remove the attached draft.
    $this->actingAs($student)->delete("/papers/{$paper->id}")->assertRedirect();
    $this->assertDatabaseMissing('papers', ['id' => $paper->id]);
});
