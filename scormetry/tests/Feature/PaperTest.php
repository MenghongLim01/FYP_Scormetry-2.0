<?php

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

    $this->actingAs($student)
        ->post('/papers', [
            'team_id' => $team->id,
            'subject_id' => $subject->id,
            'file' => UploadedFile::fake()->create('paper.pdf', 1024, 'application/pdf'),
        ])
        ->assertRedirect('/papers');

    $this->assertDatabaseHas('papers', [
        'team_id' => $team->id,
        'subject_id' => $subject->id,
        'visibility_status' => 'submitted',
    ]);
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
