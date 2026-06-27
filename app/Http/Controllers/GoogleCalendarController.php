<?php

namespace App\Http\Controllers;

use App\Jobs\RemoveDefenseCalendarEvent;
use App\Jobs\SyncDefenseCalendarEvent;
use App\Models\DefenseAttemptReviewer;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleCalendarController extends Controller
{
    /**
     * Start the optional Google Calendar connection flow. This is separate from
     * login and is the only place the calendar scope is requested.
     */
    public function connect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl($this->redirectUrl())
            ->scopes(['https://www.googleapis.com/auth/calendar.events'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->redirectUrl())
                ->user();
        } catch (InvalidStateException) {
            return redirect()->route('teams.assigned')
                ->with('error', 'Google Calendar connection failed. Please try again.');
        }

        // Hard rule: the connected calendar must belong to the same Google
        // account used to sign in to Scormetry. No manual email entry allowed.
        if (strtolower((string) $googleUser->getEmail()) !== strtolower((string) $user->email)) {
            return redirect()->route('teams.assigned')
                ->with('error', 'Please connect the same Google account you use to sign in to Scormetry.');
        }

        if (! $googleUser->refreshToken) {
            // No refresh token means we can't keep syncing; ask the user to retry
            // (Google only returns it on the first consent, so revoke + retry).
            $existing = $user->googleCalendarConnection;

            if (! $existing || ! $existing->refresh_token) {
                return redirect()->route('teams.assigned')
                    ->with('error', 'Could not complete Google Calendar connection. Please remove Scormetry from your Google account permissions and try again.');
            }
        }

        GoogleCalendarConnection::updateOrCreate(
            ['user_id' => $user->id],
            array_filter([
                'google_email' => $googleUser->getEmail(),
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
                'connected_at' => now(),
                'disconnected_at' => null,
            ], fn ($value) => $value !== null),
        );

        // Back-fill: push every already-approved, scheduled defense the judge is
        // assigned to — otherwise existing sessions never appear until a schedule
        // is next edited (which is why a freshly connected calendar looked empty).
        DefenseAttemptReviewer::with('attempt')
            ->where('reviewer_id', $user->id)
            ->where('status', 'active')
            ->whereHas('attempt', fn ($query) => $query->whereNotNull('defense_date')->whereNotNull('defense_time'))
            ->get()
            ->each(fn (DefenseAttemptReviewer $assignment) => SyncDefenseCalendarEvent::dispatch(
                $assignment->defense_attempt_id,
                $user->id,
                $assignment->committee_role,
            ));

        return redirect()->route('teams.assigned')
            ->with('success', 'Google Calendar connected. Your approved defense sessions will now appear in your calendar.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $connection = $user->googleCalendarConnection;

        if ($connection) {
            // Remove already-synced events from the user's calendar.
            GoogleCalendarEvent::where('user_id', $user->id)
                ->whereNotNull('google_event_id')
                ->get()
                ->each(function (GoogleCalendarEvent $event) use ($user) {
                    RemoveDefenseCalendarEvent::dispatch($user->id, $event->google_event_id);
                });

            // Best-effort revoke at Google.
            if ($connection->refresh_token) {
                try {
                    Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                        'token' => $connection->refresh_token,
                    ]);
                } catch (\Throwable) {
                    // Ignore — local disconnect still proceeds.
                }
            }

            $connection->update([
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
                'disconnected_at' => now(),
            ]);
        }

        return redirect()->route('teams.assigned')
            ->with('success', 'Google Calendar disconnected.');
    }

    private function redirectUrl(): string
    {
        $redirect = (string) config('services.google.calendar_redirect');

        return str_starts_with($redirect, 'http') ? $redirect : url($redirect);
    }
}
