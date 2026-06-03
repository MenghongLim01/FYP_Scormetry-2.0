<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

it('redirects to google oauth', function () {
    $this->get('/auth/google')
        ->assertRedirect();
});

it('creates a new user from google oauth callback', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google-123';
    $socialiteUser->name = 'John Doe';
    $socialiteUser->email = 'john@example.com';
    $socialiteUser->token = 'token';

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'google_id' => 'google-123',
        'role' => 'student',
    ]);
});

it('links existing user during google oauth callback', function () {
    $user = User::factory()->student()->create(['email' => 'existing@example.com']);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google-456';
    $socialiteUser->name = $user->name;
    $socialiteUser->email = 'existing@example.com';
    $socialiteUser->token = 'token';

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')
        ->assertRedirect();

    expect($user->fresh()->google_id)->toBe('google-456');
});

it('logs in existing user with google_id', function () {
    $user = User::factory()->student()->create([
        'google_id' => 'google-789',
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google-789';
    $socialiteUser->name = $user->name;
    $socialiteUser->email = $user->email;
    $socialiteUser->token = 'token';

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});
