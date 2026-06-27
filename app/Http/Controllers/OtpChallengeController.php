<?php

namespace App\Http\Controllers;

use App\Mail\LoginOtpMail;
use App\Support\LoginOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class OtpChallengeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->get('otp.pending')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/OtpChallenge', [
            'maskedEmail' => $this->maskEmail((string) $request->user()->email),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        if (! LoginOtp::verify($request->user(), $validated['code'])) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired. Request a new one if needed.']);
        }

        $request->session()->forget('otp.pending');

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $code = LoginOtp::generate($request->user());
        Mail::to($request->user())->queue(new LoginOtpMail($code));

        return back()->with('success', 'A new code has been sent to your email.');
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $maskedName = mb_strlen($name) <= 2
            ? str_repeat('*', mb_strlen($name))
            : mb_substr($name, 0, 1).str_repeat('*', max(1, mb_strlen($name) - 2)).mb_substr($name, -1);

        return $maskedName.'@'.$domain;
    }
}
