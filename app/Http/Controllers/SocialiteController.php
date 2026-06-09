<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\SubjectInvitation;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->has('role')) {
            session(['oauth_role' => $request->input('role')]);
        }

        // Force the Google account chooser so we always start a fresh,
        // stateful flow (avoids silent "prompt=none" re-auth callbacks
        // that arrive without a matching session state).
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // Stale/replayed callback (e.g. reloaded callback URL or a silent
            // re-auth). Send the user back to start a clean login.
            return redirect()->route('login')
                ->with('error', 'Your Google sign-in session expired. Please try again.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            return $this->loginGoogleUser($user);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update(['google_id' => $googleUser->getId()]);

            return $this->loginGoogleUser($user);
        }

        $role = session()->pull('oauth_role', 'student');
        $status = $this->resolveStatus($googleUser->getEmail());

        $user = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'role' => in_array($role, ['student', 'teacher']) ? $role : 'student',
            'status' => $status,
        ]);

        return $this->loginGoogleUser($user);
    }

    /**
     * Log a Google-authenticated user in. Google already verifies the email, so
     * we mark it verified here — this stops the email-verification gate (and its
     * /email/verify page) from ever blocking a Google sign-in. We also apply any
     * pending reviewer invitations on every login (not just first sign-up), so a
     * reviewer invited by email after they already had an account still gets in.
     */
    private function loginGoogleUser(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $this->applyPendingInvitations($user);

        Auth::login($user, remember: true);

        return $this->redirectAfterLogin($user);
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        if ($user->isPending()) {
            return redirect()->route('pending-approval');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function resolveStatus(string $email): string
    {
        $domain = AppSetting::get('school_email_domain');

        if ($domain && str_ends_with(strtolower($email), '@'.strtolower(trim($domain, '@')))) {
            return 'approved';
        }

        return $domain ? 'pending' : 'approved';
    }

    private function applyPendingInvitations(User $user): void
    {
        $pendingInvitations = SubjectInvitation::where('email', $user->email)
            ->whereNull('accepted_at')
            ->get();

        foreach ($pendingInvitations as $invitation) {
            SubjectMember::updateOrCreate(
                ['subject_id' => $invitation->subject_id, 'user_id' => $user->id],
                [
                    'role' => $invitation->committee_role,
                    'status' => 'approved',
                    'role_label' => $invitation->role_label,
                ],
            );
            $invitation->update(['accepted_at' => now()]);

            // Subject invitations are always for reviewers — give them a
            // teacher-level account so they get the reviewer experience.
            if ($user->role === 'student') {
                $user->update(['role' => 'teacher']);
            }
        }
    }
}
