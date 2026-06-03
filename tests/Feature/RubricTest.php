<?php

use App\Models\DefensePeriod;
use App\Models\Rubric;
use App\Models\RubricChangeLog;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use App\Services\RubricStructureExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows teacher to upload a rubric', function () {
    Storage::fake('private');

    $extractor = Mockery::mock(RubricStructureExtractor::class);
    $extractor->shouldReceive('extractFromPdf')
        ->once()
        ->andReturn([
            ['criteria' => 'Originality', 'max_score' => 5, 'weight' => 40],
            ['criteria' => 'Methodology', 'max_score' => 5, 'weight' => 60],
        ]);

    $this->app->instance(RubricStructureExtractor::class, $extractor);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/rubrics", [
            'pdf' => UploadedFile::fake()->create('rubric.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect("/subjects/{$subject->id}");

    $this->assertDatabaseHas('rubrics', [
        'subject_id' => $subject->id,
        'status' => 'pending_verification',
    ]);

    $rubric = Rubric::query()->where('subject_id', $subject->id)->firstOrFail();

    expect($rubric->structure_json)->toHaveCount(2)
        ->and($rubric->structure_json[0]['criteria'])->toBe('Originality');
});

it('allows teacher to upload a rubric for a custom defense period', function () {
    Storage::fake('private');

    $extractor = Mockery::mock(RubricStructureExtractor::class);
    $extractor->shouldReceive('extractFromPdf')
        ->once()
        ->andReturn([
            ['criteria' => 'Demo Quality', 'max_score' => 10, 'weight' => 100],
        ]);

    $this->app->instance(RubricStructureExtractor::class, $extractor);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/rubrics", [
            'use_custom_period' => 1,
            'custom_period_name' => 'Demo Day',
            'pdf' => UploadedFile::fake()->create('demo-rubric.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect("/subjects/{$subject->id}");

    $period = DefensePeriod::where('subject_id', $subject->id)
        ->where('name', 'Demo Day')
        ->firstOrFail();

    $this->assertDatabaseHas('rubrics', [
        'subject_id' => $subject->id,
        'defense_period_id' => $period->id,
        'status' => 'pending_verification',
    ]);

    expect($team->defenseAttempts()->where('defense_period_id', $period->id)->exists())->toBeTrue();
});

it('shows rubric details', function () {
    $teacher = User::factory()->teacher()->create();
    $rubric = Rubric::factory()->pending()->create([
        'subject_id' => Subject::factory()->for($teacher, 'teacher'),
    ]);

    $this->actingAs($teacher)
        ->get("/rubrics/{$rubric->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('rubrics/Show')
            ->has('rubric')
        );
});

it('allows teacher to update rubric structure', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->pending()->create();

    $this->actingAs($teacher)
        ->patch("/rubrics/{$rubric->id}", [
            'structure_json' => [
                ['criteria' => 'Quality', 'max_score' => 4, 'weight' => 50],
                ['criteria' => 'Presentation', 'max_score' => 4, 'weight' => 50],
            ],
        ])
        ->assertRedirect("/rubrics/{$rubric->id}");

    expect($rubric->fresh()->structure_json)->toHaveCount(2);
});

it('prevents editing locked rubrics', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->locked()->create();

    $this->actingAs($teacher)
        ->patch("/rubrics/{$rubric->id}", [
            'structure_json' => [
                ['criteria' => 'Quality', 'max_score' => 4, 'weight' => 100],
            ],
        ])
        ->assertRedirect();

    expect($rubric->fresh()->status)->toBe('locked');
});

it('allows teacher to approve and lock a rubric', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->pending()->create();

    $this->actingAs($teacher)
        ->post("/rubrics/{$rubric->id}/approve")
        ->assertRedirect("/subjects/{$subject->id}");

    expect($rubric->fresh()->status)->toBe('locked');
});

it('allows teacher to remove a rubric and upload again', function () {
    Storage::fake('private');

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $rubric = Rubric::factory()->for($subject)->pending()->create([
        'pdf_path' => 'rubrics/old-rubric.pdf',
    ]);

    Storage::disk('private')->put('rubrics/old-rubric.pdf', 'pdf-binary');

    $this->actingAs($teacher)
        ->delete("/rubrics/{$rubric->id}")
        ->assertRedirect("/subjects/{$subject->id}/rubrics/create");

    $this->assertDatabaseMissing('rubrics', [
        'id' => $rubric->id,
    ]);

    Storage::disk('private')->assertMissing('rubrics/old-rubric.pdf');
});

it('requires confirmation before replacing a locked rubric PDF', function () {
    Storage::fake('private');

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
    $rubric = Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $period->id,
        'pdf_path' => 'rubrics/locked-rubric.pdf',
    ]);

    Storage::disk('private')->put('rubrics/locked-rubric.pdf', 'old-pdf');

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/rubrics", [
            'defense_period_id' => $period->id,
            'pdf' => UploadedFile::fake()->create('new-rubric.pdf', 500, 'application/pdf'),
        ])
        ->assertSessionHasErrors('pdf');

    expect($rubric->fresh()->status)->toBe('locked');
    Storage::disk('private')->assertExists('rubrics/locked-rubric.pdf');
});

it('allows a confirmed new PDF upload to replace a locked rubric and reopens verification', function () {
    Storage::fake('private');

    $extractor = Mockery::mock(RubricStructureExtractor::class);
    $extractor->shouldReceive('extractFromPdf')
        ->once()
        ->andReturn([
            ['criteria' => 'Updated Content', 'max_score' => 60, 'weight' => 60],
            ['criteria' => 'Updated Presentation', 'max_score' => 40, 'weight' => 40],
        ]);

    $this->app->instance(RubricStructureExtractor::class, $extractor);

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
    $rubric = Rubric::factory()->for($subject)->locked()->create([
        'defense_period_id' => $period->id,
        'pdf_path' => 'rubrics/locked-rubric.pdf',
        'structure_json' => [
            ['criteria' => 'Old Content', 'max_score' => 100, 'weight' => 100],
        ],
    ]);

    Storage::disk('private')->put('rubrics/locked-rubric.pdf', 'old-pdf');

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/rubrics", [
            'defense_period_id' => $period->id,
            'replace_locked' => 1,
            'pdf' => UploadedFile::fake()->create('new-rubric.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect("/rubrics/{$rubric->id}");

    $rubric->refresh();

    expect($rubric->status)->toBe('pending_verification')
        ->and($rubric->structure_json)->toHaveCount(2)
        ->and($rubric->structure_json[0]['criteria'])->toBe('Updated Content')
        ->and(RubricChangeLog::where('rubric_id', $rubric->id)->exists())->toBeTrue();

    Storage::disk('private')->assertMissing('rubrics/locked-rubric.pdf');
});
