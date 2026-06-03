<?php

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('does not challenge users who have not enabled email OTP', function () {
    Mail::fake();
    $user = User::factory()->create([
        'otp_login_enabled' => false,
        'status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->actingAs($user); // simulate the resulting session

    $this->get('/dashboard')->assertOk();
    Mail::assertNothingSent();
});

it('emails a code and gates the app for OTP-enabled users until verified', function () {
    Mail::fake();
    $user = User::factory()->create([
        'otp_login_enabled' => true,
        'status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    Mail::assertSent(LoginOtpMail::class, fn ($mail) => $mail->hasTo($user->email));

    // gated until verified
    $this->get('/dashboard')->assertRedirect('/otp-challenge');
    $this->get('/otp-challenge')->assertOk();
});

it('lets the user in after entering the correct code', function () {
    Mail::fake();
    $user = User::factory()->create([
        'otp_login_enabled' => true,
        'status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $code = null;
    Mail::assertSent(LoginOtpMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->post('/otp-challenge', ['code' => $code])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
});

it('rejects an incorrect code', function () {
    Mail::fake();
    $user = User::factory()->create([
        'otp_login_enabled' => true,
        'status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post('/otp-challenge', ['code' => '000001'])->assertSessionHasErrors('code');
    $this->get('/dashboard')->assertRedirect('/otp-challenge'); // still gated
});

it('lets a user turn the email OTP on and off from settings', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->patch('/settings/security/otp', ['enabled' => true])->assertRedirect();
    expect($user->fresh()->otp_login_enabled)->toBeTrue();

    $this->actingAs($user)->patch('/settings/security/otp', ['enabled' => false])->assertRedirect();
    expect($user->fresh()->otp_login_enabled)->toBeFalse();
});
