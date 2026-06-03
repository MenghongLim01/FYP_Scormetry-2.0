<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Support\LoginOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Security', [
            'otpLoginEnabled' => (bool) $request->user()->otp_login_enabled,
        ]);
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back();
    }

    /**
     * Turn the optional email login code (OTP) on or off for the current user.
     */
    public function updateOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update(['otp_login_enabled' => $validated['enabled']]);

        if (! $validated['enabled']) {
            LoginOtp::clear($request->user());
        }

        return back()->with('success', $validated['enabled']
            ? "Email login codes are now ON. You'll be asked for a code from your email each time you sign in."
            : 'Email login codes are now OFF.');
    }
}
