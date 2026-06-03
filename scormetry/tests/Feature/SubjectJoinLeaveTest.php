<?php

use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;

it('generates a join_code when a teacher creates a subject', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post('/subjects', [
            'title' => 'FYP 2026',
            'passing_score' => 70,
        ])
        ->assertRedirect();

    $subject = Subject::where('teacher_id', $teacher->id)->first();
    expect($subject->join_code)->not->toBeNull()->toHaveLength(6);
});

it('allows a student to join a subject using the classroom code', function () {
    $subject = Subject::factory()->create(['join_code' => 'ABCDEF']);
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post('/subjects/join', ['join_code' => 'ABCDEF'])
        ->assertRedirect("/subjects/{$subject->id}");

    expect($subject->fresh()->students->contains('id', $student->id))->toBeTrue();
});

it('normalises the join code to uppercase before lookup', function () {
    $subject = Subject::factory()->create(['join_code' => 'ZXCVBN']);
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post('/subjects/join', ['join_code' => 'zxcvbn'])
        ->assertRedirect("/subjects/{$subject->id}");
});

it('returns 404 when join code does not match any subject', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post('/subjects/join', ['join_code' => 'XXXXXX'])
        ->assertStatus(404);
});

it('allows a student to leave a subject they are enrolled in', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->student()->create();
    $subject->students()->attach($student);

    $this->actingAs($student)
        ->delete("/subjects/{$subject->id}/leave")
        ->assertRedirect('/subjects');

    expect($subject->fresh()->students)->toHaveCount(0);
});

it('removes the student from their team when they leave the subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $subject->students()->attach($student);
    $team->members()->attach($student);

    $this->actingAs($student)
        ->delete("/subjects/{$subject->id}/leave")
        ->assertRedirect('/subjects');

    expect($team->fresh()->members)->toHaveCount(0);
});

it('prevents a teacher from leaving their own subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->delete("/subjects/{$subject->id}/leave")
        ->assertForbidden();
});

it('allows a reviewer to leave a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $reviewer = User::factory()->teacher()->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'adviser']);

    $this->actingAs($reviewer)
        ->delete("/subjects/{$subject->id}/leave")
        ->assertRedirect('/subjects');

    expect($subject->fresh()->reviewers)->toHaveCount(0);
});

it('generates a reviewer_code when a teacher creates a subject', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post('/subjects', [
            'title' => 'FYP 2026',
            'passing_score' => 70,
        ])
        ->assertRedirect();

    $subject = Subject::where('teacher_id', $teacher->id)->first();
    expect($subject->reviewer_code)->not->toBeNull()->toHaveLength(6);
});

it('allows a reviewer to join a subject using the reviewer code', function () {
    $subject = Subject::factory()->create(['reviewer_code' => 'REVABC']);
    $reviewer = User::factory()->teacher()->create();

    $this->actingAs($reviewer)
        ->post('/subjects/join-as-reviewer', [
            'reviewer_code' => 'REVABC',
            'committee_role' => 'adviser',
        ])
        ->assertRedirect("/subjects/{$subject->id}");

    $member = SubjectMember::where('subject_id', $subject->id)
        ->where('user_id', $reviewer->id)
        ->first();
    expect($member)->not->toBeNull()
        ->and($member->role)->toBe('adviser');
});

it('normalises the reviewer code to uppercase before lookup', function () {
    $subject = Subject::factory()->create(['reviewer_code' => 'ZXCREV']);
    $reviewer = User::factory()->teacher()->create();

    $this->actingAs($reviewer)
        ->post('/subjects/join-as-reviewer', [
            'reviewer_code' => 'zxcrev',
            'committee_role' => 'guest_panel',
        ])
        ->assertRedirect("/subjects/{$subject->id}");
});

it('returns 404 when reviewer code does not match any subject', function () {
    $reviewer = User::factory()->teacher()->create();

    $this->actingAs($reviewer)
        ->post('/subjects/join-as-reviewer', [
            'reviewer_code' => 'XXXXXX',
            'committee_role' => 'adviser',
        ])
        ->assertStatus(404);
});

it('prevents the subject owner from joining as a reviewer', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create(['reviewer_code' => 'OWNABC']);

    $this->actingAs($teacher)
        ->post('/subjects/join-as-reviewer', [
            'reviewer_code' => 'OWNABC',
            'committee_role' => 'adviser',
        ])
        ->assertForbidden();
});

it('validates committee_role when joining as reviewer', function () {
    $subject = Subject::factory()->create(['reviewer_code' => 'VALREV']);
    $reviewer = User::factory()->teacher()->create();

    $this->actingAs($reviewer)
        ->post('/subjects/join-as-reviewer', [
            'reviewer_code' => 'VALREV',
            'committee_role' => 'invalid_role',
        ])
        ->assertSessionHasErrors('committee_role');
});
