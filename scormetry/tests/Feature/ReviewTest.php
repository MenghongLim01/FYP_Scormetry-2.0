<?php

use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;

it('shows review creation form', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}/reviews/create")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/Create')
            ->has('paper')
        );
});

it('allows teacher to submit a review', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 3],
                ['criteria' => 'Presentation Quality', 'score' => 4],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $this->assertDatabaseHas('reviews', [
        'paper_id' => $paper->id,
        'reviewer_id' => $teacher->id,
        'is_submitted' => true,
    ]);

    expect($paper->fresh()->final_score)->not->toBeNull();
});

it('recalculates final score with multiple reviews', function () {
    $teacher1 = User::factory()->teacher()->create();
    $teacher2 = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher1, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $subject->reviewers()->attach($teacher2, ['role' => 'adviser', 'status' => 'approved']);
    $team->members()->attach($teacher2);

    Review::factory()->for($paper)->submitted()->create([
        'reviewer_id' => $teacher1->id,
        'scores_json' => [
            ['criteria' => 'Content', 'score' => 4],
            ['criteria' => 'Presentation', 'score' => 2],
        ],
    ]);

    $this->actingAs($teacher2)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content', 'score' => 3],
                ['criteria' => 'Presentation', 'score' => 3],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    // Teacher1: 4+2=6, Teacher2: 3+3=6, avg = 6
    expect((float) $paper->fresh()->final_score)->toBe(6.0);
});

it('shows review details', function () {
    $teacher = User::factory()->teacher()->create();
    $review = Review::factory()->submitted()->create(['reviewer_id' => $teacher->id]);

    $this->actingAs($teacher)
        ->get("/reviews/{$review->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/Show')
            ->has('review')
        );
});

it('stores committee role when reviewer is attached to subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $subject->reviewers()->attach($teacher->id, ['role' => 'adviser', 'status' => 'approved']);

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 3],
                ['criteria' => 'Presentation Quality', 'score' => 4],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $this->assertDatabaseHas('reviews', [
        'paper_id' => $paper->id,
        'reviewer_id' => $teacher->id,
        'committee_role' => 'adviser',
        'is_submitted' => true,
    ]);
});

it('stores a review comment', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 3],
            ],
            'comment' => '<p>Great work on the <strong>methodology</strong> section.</p>',
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $review = Review::where('paper_id', $paper->id)->first();
    expect($review->comment)->toContain('<strong>methodology</strong>');
});

it('sanitizes review comment html', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 3],
            ],
            'comment' => '<p>Good</p><script>alert("xss")</script><img onerror="alert(1)">',
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $review = Review::where('paper_id', $paper->id)->first();
    expect($review->comment)->not->toContain('<script>')
        ->and($review->comment)->not->toContain('<img')
        ->and($review->comment)->toContain('<p>Good</p>');
});

it('passes paperPdfUrl and existingReview to create page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}/reviews/create")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/Create')
            ->has('paper')
            ->has('paperPdfUrl')
            ->where('existingReview', null)
        );
});

it('passes existing review when reviewer has already reviewed', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Rubric::factory()->for($subject)->locked()->create();
    $team = Team::factory()->for($subject)->create();
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();
    Review::factory()->for($paper)->submitted()->create(['reviewer_id' => $teacher->id]);

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}/reviews/create")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('reviews/Create')
            ->where('existingReview.reviewer_id', $teacher->id)
        );
});

it('prevents student from viewing review for unpublished paper', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create();
    $review = Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($student)
        ->get("/reviews/{$review->id}")
        ->assertForbidden();
});

it('allows student to view review for published paper they are team member of', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->create(['visibility_status' => 'published']);
    $review = Review::factory()->for($paper)->submitted()->create();

    $this->actingAs($student)
        ->get("/reviews/{$review->id}")
        ->assertSuccessful();
});
