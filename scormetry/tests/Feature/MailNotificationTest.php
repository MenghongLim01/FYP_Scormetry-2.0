<?php

use App\Mail\DefenseScheduledMail;
use App\Mail\PaperPublishedMail;
use App\Mail\PaperSubmittedMail;
use App\Mail\UserApprovedMail;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('sends paper submitted mail to subject teacher', function () {
    Mail::fake();
    Storage::fake('private');

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);
    $student->enrolledSubjects()->attach($subject, ['status' => 'approved']);

    $this->actingAs($student)
        ->post('/papers', [
            'team_id' => $team->id,
            'subject_id' => $subject->id,
            'file' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
        ]);

    Mail::assertSent(PaperSubmittedMail::class, function ($mail) use ($teacher) {
        return $mail->hasTo($teacher->email);
    });
});

it('sends user approved mail when admin approves user', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $pendingUser = User::factory()->student()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post("/admin/users/{$pendingUser->id}/approve");

    Mail::assertSent(UserApprovedMail::class, function ($mail) use ($pendingUser) {
        return $mail->hasTo($pendingUser->email);
    });
});

it('sends paper published mail to team members', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);
    $paper = Paper::factory()->for($subject)->for($team, 'team')->create([
        'final_score' => 80,
        'visibility_status' => 'submitted',
    ]);

    $this->actingAs($teacher)
        ->post("/papers/{$paper->id}/publish");

    Mail::assertSent(PaperPublishedMail::class, function ($mail) use ($student) {
        return $mail->hasTo($student->email);
    });
});

it('queues schedule mail with calendar invite only for attempt participants', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->teacher()->create();
    $otherReviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $subject->reviewers()->attach($otherReviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Midterm Defense',
        'type' => 'midterm',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);
    $attempt->reviewerAssignments()->create([
        'reviewer_id' => $reviewer->id,
        'committee_role' => 'Guest Panel',
        'status' => 'active',
    ]);

    $this->actingAs($teacher)
        ->patch("/defense-attempts/{$attempt->id}", [
            'defense_date' => now()->addWeek()->format('Y-m-d'),
            'defense_time' => '09:00',
            'defense_duration' => 60,
            'defense_room' => 'Room A',
            'score_deadline_at' => now()->addWeek()->addDay()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect();

    Mail::assertQueued(DefenseScheduledMail::class, 2);
    Mail::assertQueued(DefenseScheduledMail::class, fn (DefenseScheduledMail $mail) => $mail->hasTo($student->email));
    Mail::assertQueued(DefenseScheduledMail::class, function (DefenseScheduledMail $mail) use ($reviewer) {
        if (! $mail->hasTo($reviewer->email) || $mail->changeType !== 'scheduled') {
            return false;
        }

        [$payload, $options] = $mail->attachments()[0]->attachWith(
            fn () => null,
            fn ($data, $attachment) => [$data(), ['mime' => $attachment->mime]],
        );

        return str_contains($payload, 'METHOD:REQUEST')
            && str_contains($payload, 'BEGIN:VEVENT')
            && str_contains($payload, 'SUMMARY:Scormetry Defense')
            && str_contains($options['mime'], 'text/calendar');
    });
    Mail::assertNotQueued(DefenseScheduledMail::class, fn (DefenseScheduledMail $mail) => $mail->hasTo($teacher->email));
    Mail::assertNotQueued(DefenseScheduledMail::class, fn (DefenseScheduledMail $mail) => $mail->hasTo($otherReviewer->email));
});

it('queues calendar invite when a reviewer is approved after schedule is set', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $otherReviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $subject->reviewers()->attach($otherReviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 2,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $team = Team::factory()->for($subject)->create();
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
        'defense_date' => now()->addWeek()->format('Y-m-d'),
        'defense_time' => '14:00',
        'defense_duration' => 60,
        'defense_room' => 'Room C',
        'status' => 'scheduled',
    ]);

    $this->actingAs($reviewer)
        ->post("/defense-attempts/{$attempt->id}/reviewers/request")
        ->assertRedirect();

    $this->actingAs($teacher)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}/approve")
        ->assertRedirect();

    Mail::assertQueued(DefenseScheduledMail::class, 1);
    Mail::assertQueued(DefenseScheduledMail::class, function (DefenseScheduledMail $mail) use ($reviewer) {
        if (! $mail->hasTo($reviewer->email) || $mail->changeType !== 'scheduled') {
            return false;
        }

        [$payload] = $mail->attachments()[0]->attachWith(
            fn () => null,
            fn ($data, $attachment) => [$data(), ['mime' => $attachment->mime]],
        );

        return str_contains($payload, 'METHOD:REQUEST')
            && str_contains($payload, 'Room C');
    });
    Mail::assertNotQueued(DefenseScheduledMail::class, fn (DefenseScheduledMail $mail) => $mail->hasTo($teacher->email));
    Mail::assertNotQueued(DefenseScheduledMail::class, fn (DefenseScheduledMail $mail) => $mail->hasTo($otherReviewer->email));
});

it('queues cancelled calendar invite when a defense schedule is cleared', function () {
    Mail::fake();

    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
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
        'defense_date' => now()->addWeek()->format('Y-m-d'),
        'defense_time' => '10:00',
        'defense_duration' => 60,
        'defense_room' => 'Room B',
        'status' => 'scheduled',
    ]);

    $this->actingAs($teacher)
        ->patch("/defense-attempts/{$attempt->id}", [
            'defense_date' => null,
            'defense_time' => null,
            'defense_duration' => null,
            'defense_room' => null,
            'score_deadline_at' => null,
        ])
        ->assertRedirect();

    Mail::assertQueued(DefenseScheduledMail::class, function (DefenseScheduledMail $mail) use ($student) {
        if (! $mail->hasTo($student->email) || $mail->changeType !== 'cancelled') {
            return false;
        }

        [$payload] = $mail->attachments()[0]->attachWith(
            fn () => null,
            fn ($data, $attachment) => [$data(), ['mime' => $attachment->mime]],
        );

        return str_contains($payload, 'METHOD:CANCEL')
            && str_contains($payload, 'STATUS:CANCELLED');
    });
});
