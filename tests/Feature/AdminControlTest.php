<?php

use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\ReviewCorrectionLog;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;

function adminControlFixture(): array
{
    $owner = User::factory()->teacher()->create(['name' => 'Original Owner']);
    $subject = Subject::factory()->create(['teacher_id' => $owner->id, 'passing_score' => 50]);
    SubjectMember::factory()->create([
        'subject_id' => $subject->id,
        'user_id' => $owner->id,
        'role' => 'fyp_instructor',
        'role_label' => 'FYP Instructor',
        'status' => 'approved',
    ]);

    $team = Team::factory()->create(['subject_id' => $subject->id, 'name' => 'Team 1']);
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'score_scale' => 'points_100',
        'passing_score' => 50,
        'status' => 'open',
    ]);

    Rubric::factory()->locked()->create([
        'subject_id' => $subject->id,
        'defense_period_id' => $period->id,
    ]);

    $attempt = DefenseAttempt::create([
        'defense_period_id' => $period->id,
        'team_id' => $team->id,
        'label' => 'Defense Session 1',
        'attempt_number' => 1,
        'attempt_type' => 'regular',
        'defense_date' => now()->addWeek()->toDateString(),
        'defense_time' => '09:00',
        'defense_duration' => 30,
        'defense_room' => 'Room 301',
        'status' => 'scheduled',
    ]);

    return compact('owner', 'subject', 'team', 'period', 'attempt');
}

it('lets an admin open system health and classroom control pages', function () {
    $admin = User::factory()->admin()->create();
    ['subject' => $subject] = adminControlFixture();

    $this->actingAs($admin)
        ->get(route('admin.system-health.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/SystemHealth'));

    $this->actingAs($admin)
        ->get(route('admin.classrooms.control', $subject))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/ClassroomControl'));
});

it('lets an admin transfer the classroom owner and keeps every defense attempt accessible', function () {
    $admin = User::factory()->admin()->create();
    $newOwner = User::factory()->teacher()->create(['name' => 'New Owner']);
    ['owner' => $owner, 'subject' => $subject, 'attempt' => $attempt] = adminControlFixture();

    $this->actingAs($admin)
        ->patch(route('admin.classrooms.owner.update', $subject), [
            'teacher_id' => $newOwner->id,
        ])
        ->assertRedirect();

    expect($subject->fresh()->teacher_id)->toBe($newOwner->id);

    $this->assertDatabaseHas('subject_members', [
        'subject_id' => $subject->id,
        'user_id' => $newOwner->id,
        'role' => 'fyp_instructor',
        'status' => 'approved',
    ]);

    $this->assertDatabaseHas('defense_attempt_reviewers', [
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $newOwner->id,
        'committee_role' => 'fyp_instructor',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('defense_attempt_reviewers', [
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $owner->id,
        'committee_role' => 'advisor',
        'status' => 'active',
    ]);
});

it('lets an admin add an approved student or reviewer into a classroom', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->teacher()->create();
    ['subject' => $subject, 'team' => $team] = adminControlFixture();

    $this->actingAs($admin)
        ->post(route('admin.classrooms.members.add', $subject), [
            'user_id' => $student->id,
            'member_type' => 'student',
            'team_id' => $team->id,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.classrooms.members.add', $subject), [
            'user_id' => $reviewer->id,
            'member_type' => 'reviewer',
            'reviewer_role' => 'technical_examiner',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subject_members', [
        'subject_id' => $subject->id,
        'user_id' => $student->id,
        'role' => 'student',
        'status' => 'approved',
    ]);

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $student->id,
    ]);

    $this->assertDatabaseHas('subject_members', [
        'subject_id' => $subject->id,
        'user_id' => $reviewer->id,
        'role' => 'technical_examiner',
        'role_label' => 'Technical examiner',
        'status' => 'approved',
    ]);
});

it('lets an admin correct a submitted reviewer score and records audit history', function () {
    $admin = User::factory()->admin()->create();
    $reviewer = User::factory()->teacher()->create();
    ['subject' => $subject, 'team' => $team, 'attempt' => $attempt] = adminControlFixture();

    $paper = Paper::factory()->submitted()->create([
        'subject_id' => $subject->id,
        'team_id' => $team->id,
        'defense_attempt_id' => $attempt->id,
    ]);

    $assignment = DefenseAttemptReviewer::create([
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'technical_examiner',
        'status' => 'active',
    ]);

    $review = Review::factory()->submitted()->create([
        'paper_id' => $paper->id,
        'defense_attempt_id' => $attempt->id,
        'defense_attempt_reviewer_id' => $assignment->id,
        'reviewer_id' => $reviewer->id,
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => 30, 'max_score' => 50, 'weight' => 50, 'comment' => 'Before'],
            ['criteria' => 'Presentation Quality', 'score' => 30, 'max_score' => 50, 'weight' => 50, 'comment' => null],
        ],
        'comment' => 'Original feedback',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reviews.correct-score', $review), [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 50, 'max_score' => 50, 'weight' => 50, 'comment' => 'Corrected'],
                ['criteria' => 'Presentation Quality', 'score' => 50, 'max_score' => 50, 'weight' => 50, 'comment' => 'Corrected'],
            ],
            'comment' => 'Admin corrected the submitted review.',
            'reason' => 'Wrong score was entered during defense.',
        ])
        ->assertRedirect();

    expect((float) $paper->fresh()->final_score)->toBe(100.0)
        ->and((float) $attempt->fresh()->final_score)->toBe(100.0)
        ->and(ReviewCorrectionLog::query()->where('review_id', $review->id)->count())->toBe(1);

    $this->assertDatabaseHas('review_correction_logs', [
        'review_id' => $review->id,
        'paper_id' => $paper->id,
        'defense_attempt_id' => $attempt->id,
        'corrected_by' => $admin->id,
        'reason' => 'Wrong score was entered during defense.',
    ]);
});
