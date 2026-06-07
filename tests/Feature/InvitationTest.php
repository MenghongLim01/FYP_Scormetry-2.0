<?php

use App\Mail\ReviewerAddedMail;
use App\Mail\ReviewerInvitationMail;
use App\Models\Subject;
use App\Models\SubjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('queues ReviewerAddedMail and attaches existing teacher immediately', function () {
    Mail::fake();

    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => $reviewer->email,
            'committee_role' => 'advisor',
        ])
        ->assertRedirect();

    expect($subject->fresh()->reviewers)->toHaveCount(1)
        ->and($subject->fresh()->reviewers->first()->pivot->role)->toBe('advisor');

    Mail::assertQueued(ReviewerAddedMail::class, fn ($mail) => $mail->hasTo($reviewer->email));
});

it('does not allow inviting another FYP Instructor', function () {
    Mail::fake();

    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => $reviewer->email,
            'committee_role' => 'fyp_instructor',
        ])
        ->assertSessionHasErrors('committee_role');

    $this->assertDatabaseMissing('subject_members', [
        'subject_id' => $subject->id,
        'user_id' => $reviewer->id,
        'role' => 'fyp_instructor',
    ]);

    Mail::assertNothingSent();
});

it('queues ReviewerInvitationMail and stores pending invite for unregistered email', function () {
    Mail::fake();

    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => 'newreviewer@example.com',
            'committee_role' => 'custom',
            'role_label' => 'External Examiner',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subject_invitations', [
        'subject_id' => $subject->id,
        'email' => 'newreviewer@example.com',
        'committee_role' => 'custom',
        'role_label' => 'External Examiner',
        'accepted_at' => null,
    ]);

    Mail::assertQueued(ReviewerInvitationMail::class, fn ($mail) => $mail->hasTo('newreviewer@example.com'));
});

it('applies pending invitations when user registers', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    SubjectInvitation::create([
        'subject_id' => $subject->id,
        'email' => 'pending@example.com',
        'committee_role' => 'fyp_instructor',
        'token' => SubjectInvitation::generateToken(),
    ]);

    $this->post('/register', [
        'name' => 'New Teacher',
        'email' => 'pending@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'teacher',
    ]);

    $user = User::where('email', 'pending@example.com')->firstOrFail();

    expect($subject->fresh()->reviewers)->toHaveCount(1)
        ->and($subject->fresh()->reviewers->first()->id)->toBe($user->id)
        ->and($subject->fresh()->reviewers->first()->pivot->role)->toBe('fyp_instructor');

    $this->assertDatabaseMissing('subject_invitations', [
        'email' => 'pending@example.com',
        'accepted_at' => null,
    ]);
});

it('marks an existing user as an approved reviewer when they accept an invite link', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $reviewer = User::factory()->teacher()->create();

    $invitation = SubjectInvitation::create([
        'subject_id' => $subject->id,
        'email' => $reviewer->email,
        'committee_role' => 'advisor',
        'role_label' => 'Advisor',
        'token' => $token = SubjectInvitation::generateToken(),
    ]);

    $this->actingAs($reviewer)
        ->get("/invitations/{$token}")
        ->assertRedirect(route('subjects.show', $subject->id));

    // reviewers() filters on status = 'approved', so this proves the membership
    // was created with the right status — not left as NULL.
    expect($subject->fresh()->reviewers)->toHaveCount(1)
        ->and($subject->fresh()->reviewers->first()->id)->toBe($reviewer->id)
        ->and($subject->fresh()->reviewers->first()->pivot->role)->toBe('advisor')
        ->and($subject->fresh()->reviewers->first()->pivot->role_label)->toBe('Advisor');

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('redirects to register when visiting invite link as a guest', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    SubjectInvitation::create([
        'subject_id' => $subject->id,
        'email' => 'newuser@example.com',
        'committee_role' => 'advisor',
        'token' => $token = SubjectInvitation::generateToken(),
    ]);

    $this->get("/invitations/{$token}")
        ->assertRedirect('/register');

    expect(session('invitation_token'))->toBe($token);
});
