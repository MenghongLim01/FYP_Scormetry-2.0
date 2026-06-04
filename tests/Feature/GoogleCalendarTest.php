<?php

use App\Jobs\RemoveDefenseCalendarEvent;
use App\Jobs\SyncDefenseCalendarEvent;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

function mockCalendarSocialite(string $email): void
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google-cal-1';
    $socialiteUser->name = 'Judge';
    $socialiteUser->email = $email;
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->expiresIn = 3600;

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('saves the calendar connection when the google email matches the login email', function () {
    $judge = User::factory()->teacher()->create(['email' => 'judge@example.com']);
    mockCalendarSocialite('judge@example.com');

    $this->actingAs($judge)
        ->get('/google-calendar/callback')
        ->assertRedirect(route('teams.assigned'))
        ->assertSessionHas('success');

    $connection = GoogleCalendarConnection::where('user_id', $judge->id)->first();
    expect($connection)->not->toBeNull()
        ->and($connection->google_email)->toBe('judge@example.com')
        ->and($connection->refresh_token)->toBe('refresh-token')
        ->and($connection->isActive())->toBeTrue();
});

it('rejects the connection when the google email differs from the login email', function () {
    $judge = User::factory()->teacher()->create(['email' => 'judge@example.com']);
    mockCalendarSocialite('someone-else@gmail.com');

    $this->actingAs($judge)
        ->get('/google-calendar/callback')
        ->assertRedirect(route('teams.assigned'))
        ->assertSessionHas('error', 'Please connect the same Google account you use to sign in to Scormetry.');

    expect(GoogleCalendarConnection::where('user_id', $judge->id)->exists())->toBeFalse();
});

it('marks a calendar connection as disconnected and removes synced events', function () {
    Queue::fake();
    $judge = User::factory()->teacher()->create();
    GoogleCalendarConnection::create([
        'user_id' => $judge->id,
        'google_email' => $judge->email,
        'refresh_token' => 'refresh-token',
        'connected_at' => now(),
    ]);

    $this->actingAs($judge)
        ->delete('/google-calendar/disconnect')
        ->assertRedirect(route('teams.assigned'));

    expect($judge->fresh()->googleCalendarConnection->isActive())->toBeFalse();
});

it('dispatches a calendar sync job when a reviewer is assigned', function () {
    Queue::fake();
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
    $attempt = $team->defenseAttempts()->create([
        'defense_period_id' => $period->id,
        'label' => 'Attempt 1',
        'attempt_number' => 1,
    ]);

    $this->actingAs($owner)
        ->post("/defense-attempts/{$attempt->id}/reviewers", [
            'reviewer_id' => $reviewer->id,
            'committee_role' => 'technical_examiner',
        ])
        ->assertRedirect();

    Queue::assertPushed(SyncDefenseCalendarEvent::class, fn ($job) => $job->reviewerId === $reviewer->id
        && $job->defenseAttemptId === $attempt->id);
});

it('dispatches a calendar removal job when a reviewer is unassigned', function () {
    Queue::fake();
    $owner = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($owner, 'teacher')->create();
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();

    $period = DefensePeriod::create([
        'subject_id' => $subject->id,
        'name' => 'Final Defense',
        'type' => 'final',
        'sequence' => 1,
        'passing_score' => 50,
        'status' => 'setup',
    ]);
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
    $team->members()->syncWithoutDetaching([$reviewer->id]);
    GoogleCalendarEvent::create([
        'user_id' => $reviewer->id,
        'defense_attempt_id' => $attempt->id,
        'google_event_id' => 'evt-123',
        'status' => 'synced',
        'last_synced_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$reviewer->id}")
        ->assertRedirect();

    Queue::assertPushed(RemoveDefenseCalendarEvent::class, fn ($job) => $job->reviewerId === $reviewer->id
        && $job->googleEventId === 'evt-123');
});
