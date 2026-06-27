<?php

namespace App\Listeners;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Support\LoginOtp;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;

class SendLoginOtp
{
    /**
     * When a user with email-OTP enabled logs in, mark the session as pending an
     * OTP and email them a fresh code. The EnsureOtpVerified middleware then gates
     * the app until they enter it.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || ! $user->otp_login_enabled) {
            return;
        }

        session(['otp.pending' => true]);

        $code = LoginOtp::generate($user);
        Mail::to($user)->queue(new LoginOtpMail($code));
    }
}
