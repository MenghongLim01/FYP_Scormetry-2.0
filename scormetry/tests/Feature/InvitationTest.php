<?php

use App\Mail\ReviewerAddedMail;
use App\Mail\ReviewerInvitationMail;
use App\Models\Subject;
use App\Models\SubjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('sends ReviewerAddedMail and attaches existing teacher immediately', function () {
    Mail::fake();

    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => $reviewer->email,
            'committee_role' => 'adviser',
        ])
        ->assertRedirect();

    expect($subject->fresh()->reviewers)->toHaveCount(1)
        ->and($subject->fresh()->reviewers->first()->pivot->role)->toBe('adviser');

    Mail::assertSent(ReviewerAddedMail::class, fn ($mail) => $mail->hasTo($reviewer->email));
});

it('sends ReviewerInvitationMail and stores pending invite for unregistered email', function () {
    Mail::fake();

    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => 'newreviewer@example.com',
            'committee_role' => 'guest_panel',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subject_invitations', [
        'subject_id' => $subject->id,
        'email' => 'newreviewer@example.com',
        'committee_role' => 'guest_panel',
        'accepted_at' => null,
    ]);

    Mail::assertSent(ReviewerInvitationMail::class, fn ($mail) => $mail->hasTo('newreviewer@example.com'));
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

it('redirects to register when visiting invite link as a guest', function () {
    $owner = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();

    SubjectInvitation::create([
        'subject_id' => $subject->id,
        'email' => 'newuser@example.com',
        'committee_role' => 'adviser',
        'token' => $token = SubjectInvitation::generateToken(),
    ]);

    $this->get("/invitations/{$token}")
        ->assertRedirect('/register');

    expect(session('invitation_token'))->toBe($token);
});
