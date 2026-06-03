<?php

use App\Models\DefenseAttempt;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\RubricChangeLog;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('creates simple midterm and final rounds when a subject is created', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post('/subjects', [
            'title' => 'FYP Defense',
            'description' => 'Defense workflow',
            'passing_score' => 60,
            'require_approval' => false,
        ])
        ->assertRedirect();

    $subject = Subject::where('title', 'FYP Defense')->firstOrFail();

    expect($subject->defensePeriods()->pluck('type')->all())->toBe(['midterm', 'final']);
});

it('lets reviewers request a team and requires instructor approval before access', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
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
    $paper = Paper::factory()
        ->for($subject)
        ->for($team, 'team')
        ->submitted()
        ->create(['defense_attempt_id' => $attempt->id]);

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->post("/defense-attempts/{$attempt->id}/reviewers/request")
        ->assertRedirect();

    expect($attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->value('status'))->toBe('pending')
        ->and($team->members()->where('users.id', $reviewer->id)->exists())->toBeFalse();

    $this->actingAs($owner)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}/approve")
        ->assertRedirect();

    expect($attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->value('status'))->toBe('active')
        ->and($team->members()->where('users.id', $reviewer->id)->exists())->toBeTrue();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertOk();
});

it('assigns reviewers to a defense attempt and stores reviews under that attempt', function () {
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($owner)
        ->post("/teams/{$team->id}/members", ['email' => $reviewer->email])
        ->assertRedirect();

    $attempt = DefenseAttempt::where('team_id', $team->id)->firstOrFail();

    Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $attempt->defense_period_id,
    ]);

    $paper = Paper::factory()
        ->for($subject)
        ->for($team, 'team')
        ->submitted()
        ->create(['defense_attempt_id' => $attempt->id]);

    $this->actingAs($reviewer)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 40],
                ['criteria' => 'Presentation Quality', 'score' => 45],
            ],
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $review = Review::where('paper_id', $paper->id)->firstOrFail();

    expect($review->defense_attempt_id)->toBe($attempt->id)
        ->and($review->defense_attempt_reviewer_id)->not->toBeNull();
});

it('lets students replace an attempt paper before the upload deadline only', function () {
    Storage::fake('private');

    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student, ['status' => 'approved']);
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'paper_upload_deadline_at' => now()->addDay(),
    ]);

    $this->actingAs($student)
        ->post('/papers', [
            'subject_id' => $subject->id,
            'defense_attempt_id' => $attempt->id,
            'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect('/papers');

    $firstPath = Paper::firstOrFail()->file_path;

    $this->actingAs($student)
        ->post('/papers', [
            'subject_id' => $subject->id,
            'defense_attempt_id' => $attempt->id,
            'file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect('/papers');

    expect(Paper::count())->toBe(1)
        ->and(Paper::firstOrFail()->file_path)->not->toBe($firstPath);

    $attempt->update(['paper_upload_deadline_at' => now()->subMinute()]);

    $this->actingAs($student)
        ->post('/papers', [
            'subject_id' => $subject->id,
            'defense_attempt_id' => $attempt->id,
            'file' => UploadedFile::fake()->create('late.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

it('requires confirmation and logs corrections to locked rubrics after scoring starts', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $rubric = Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $period->id,
    ]);
    $team = Team::factory()->for($subject)->create();
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);
    Review::factory()->for($paper)->submitted()->create([
        'defense_attempt_id' => $attempt->id,
    ]);

    $payload = [
        'structure_json' => [
            ['criteria' => 'Content Quality', 'max_score' => 50, 'weight' => 60],
            ['criteria' => 'Presentation Quality', 'max_score' => 50, 'weight' => 40],
        ],
        'correction_reason' => 'Fix extracted weights.',
    ];

    $this->actingAs($owner)
        ->patch("/rubrics/{$rubric->id}", $payload)
        ->assertSessionHasErrors('rubric');

    $this->actingAs($owner)
        ->patch("/rubrics/{$rubric->id}", [
            ...$payload,
            'confirm_scoring_started_change' => true,
        ])
        ->assertRedirect("/rubrics/{$rubric->id}");

    expect(RubricChangeLog::where('rubric_id', $rubric->id)->exists())->toBeTrue()
        ->and($rubric->fresh()->status)->toBe('locked');
});
