<?php

use App\Models\Paper;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

it('shows paper with pdf urls on the show page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($teacher);
    Storage::disk('private')->put('papers/test.pdf', 'fake pdf content');
    $paper = Paper::factory()->for($team)->for($subject)->create(['file_path' => 'papers/test.pdf']);

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Show')
            ->has('paperPdfUrl')
            ->has('rubricPdfUrl')
        );
});

it('serves the paper pdf to a team member', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($teacher);
    Storage::disk('private')->put('papers/test.pdf', 'fake pdf content');
    $paper = Paper::factory()->for($team)->for($subject)->create(['file_path' => 'papers/test.pdf']);

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}/pdf")
        ->assertSuccessful();
});

it('returns not found when the stored paper file is missing', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($teacher);
    $paper = Paper::factory()->for($team)->for($subject)->create(['file_path' => 'papers/missing.pdf']);

    $this->actingAs($teacher)
        ->get("/papers/{$paper->id}/pdf")
        ->assertNotFound();
});

it('denies paper pdf access to unrelated users', function () {
    $paper = Paper::factory()->create();
    Storage::disk('private')->put($paper->file_path, 'fake pdf content');
    $otherUser = User::factory()->student()->create();

    $this->actingAs($otherUser)
        ->get("/papers/{$paper->id}/pdf")
        ->assertForbidden();
});

it('serves the rubric pdf to the subject teacher', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    Storage::disk('private')->put('rubrics/test.pdf', 'fake rubric content');
    $rubric = Rubric::factory()->for($subject)->create(['pdf_path' => 'rubrics/test.pdf', 'status' => 'locked']);

    $this->actingAs($teacher)
        ->get("/rubrics/{$rubric->id}/pdf")
        ->assertSuccessful();
});

it('papers index passes reviewer_team_ids to the view', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($teacher, ['role' => 'guest_panel']);

    $this->actingAs($teacher)
        ->get('/papers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('papers/Index')
            ->has('reviewerTeamIds')
        );
});
