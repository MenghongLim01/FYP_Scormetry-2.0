<?php

use App\Mail\ReviewAutoSubmittedMail;
use App\Mail\ReviewCompletedMail;
use App\Mail\ReviewDeadlineReminderMail;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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

    $subject->reviewers()->attach($teacher2, ['role' => 'advisor', 'status' => 'approved']);
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

    $subject->reviewers()->attach($teacher->id, ['role' => 'advisor', 'status' => 'approved']);

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
        'committee_role' => 'advisor',
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

it('prevents a judge from viewing another judges review details', function () {
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
    $otherReview = Review::factory()->for($paper)->submitted()->create([
        'reviewer_id' => $otherReviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->get("/reviews/{$otherReview->id}")
        ->assertForbidden();
});

it('auto-submits completed review drafts after the score deadline', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $period->id,
    ]);
    $team = Team::factory()->for($subject)->create();
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'score_deadline_at' => now()->subMinute(),
    ]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);
    $assignment = $attempt->activeReviewerAssignments()->where('reviewer_id', $teacher->id)->firstOrFail();

    $review = Review::factory()->for($paper)->create([
        'reviewer_id' => $teacher->id,
        'defense_attempt_id' => $attempt->id,
        'defense_attempt_reviewer_id' => $assignment->id,
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => 3],
            ['criteria' => 'Presentation Quality', 'score' => 4],
        ],
        'comment' => null,
        'is_submitted' => false,
    ]);

    $this->artisan('reviews:process-deadlines')->assertExitCode(0);

    $review->refresh();
    expect($review->is_submitted)->toBeTrue()
        ->and($review->locked_at)->not->toBeNull()
        ->and($review->auto_submitted_at)->not->toBeNull()
        ->and($paper->fresh()->final_score)->not->toBeNull();

    Mail::assertSent(ReviewAutoSubmittedMail::class, fn ($mail) => $mail->hasTo($teacher->email));
    Mail::assertSent(ReviewCompletedMail::class, fn ($mail) => $mail->hasTo($teacher->email));
    Mail::assertNotSent(ReviewDeadlineReminderMail::class);
});

it('reminds only incomplete reviewers after the score deadline', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $period->id,
    ]);
    $team = Team::factory()->for($subject)->create();
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'score_deadline_at' => now()->subMinute(),
    ]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);
    $assignment = $attempt->activeReviewerAssignments()->where('reviewer_id', $teacher->id)->firstOrFail();

    $review = Review::factory()->for($paper)->create([
        'reviewer_id' => $teacher->id,
        'defense_attempt_id' => $attempt->id,
        'defense_attempt_reviewer_id' => $assignment->id,
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => 3],
            ['criteria' => 'Presentation Quality', 'score' => 0],
        ],
        'is_submitted' => false,
    ]);

    $this->artisan('reviews:process-deadlines')->assertExitCode(0);

    expect($review->fresh()->is_submitted)->toBeFalse()
        ->and($assignment->fresh()->score_deadline_reminded_at)->not->toBeNull();

    Mail::assertSent(ReviewDeadlineReminderMail::class, fn ($mail) => $mail->hasTo($teacher->email));
    Mail::assertNotSent(ReviewAutoSubmittedMail::class);
});
