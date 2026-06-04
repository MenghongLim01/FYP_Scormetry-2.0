<?php

namespace App\Services;

use App\Models\DefenseAttempt;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    /**
     * Create or update the Google Calendar event for a reviewer on a defense
     * attempt. Returns true when an event was written, false otherwise (e.g.
     * the reviewer has not connected a calendar, or the defense is unscheduled).
     */
    public function syncEvent(DefenseAttempt $attempt, User $reviewer, ?string $committeeRole = null): bool
    {
        $connection = $this->activeConnection($reviewer);

        if (! $connection) {
            return false;
        }

        $attempt->loadMissing('team.subject', 'team.members', 'period', 'papers');

        if ($attempt->defense_date === null || $attempt->defense_time === null) {
            return false;
        }

        $token = $this->freshAccessToken($connection);

        if (! $token) {
            return false;
        }

        $record = GoogleCalendarEvent::firstOrNew([
            'user_id' => $reviewer->id,
            'defense_attempt_id' => $attempt->id,
        ]);

        $payload = $this->buildEventPayload($attempt, $reviewer, $committeeRole);

        try {
            if ($record->google_event_id) {
                $response = Http::withToken($token)
                    ->put(self::EVENTS_URL.'/'.$record->google_event_id, $payload);

                // Event was deleted on Google's side — recreate it.
                if ($response->status() === 404 || $response->status() === 410) {
                    $response = Http::withToken($token)->post(self::EVENTS_URL, $payload);
                }
            } else {
                $response = Http::withToken($token)->post(self::EVENTS_URL, $payload);
            }
        } catch (\Throwable $e) {
            Log::warning('Google Calendar sync failed', [
                'user_id' => $reviewer->id,
                'defense_attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Google Calendar sync returned an error', [
                'user_id' => $reviewer->id,
                'defense_attempt_id' => $attempt->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $record->fill([
            'google_event_id' => $response->json('id') ?? $record->google_event_id,
            'status' => 'synced',
            'last_synced_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Remove a previously synced event from a reviewer's Google Calendar.
     */
    public function removeEvent(User $reviewer, string $googleEventId): void
    {
        $connection = $this->activeConnection($reviewer);

        if (! $connection) {
            return;
        }

        $token = $this->freshAccessToken($connection);

        if (! $token) {
            return;
        }

        try {
            Http::withToken($token)->delete(self::EVENTS_URL.'/'.$googleEventId);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event removal failed', [
                'user_id' => $reviewer->id,
                'google_event_id' => $googleEventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function activeConnection(User $reviewer): ?GoogleCalendarConnection
    {
        $connection = $reviewer->relationLoaded('googleCalendarConnection')
            ? $reviewer->googleCalendarConnection
            : $reviewer->googleCalendarConnection()->first();

        return $connection?->isActive() ? $connection : null;
    }

    /**
     * Return a valid access token, refreshing it via the refresh token when the
     * current one is missing or about to expire.
     */
    public function freshAccessToken(GoogleCalendarConnection $connection): ?string
    {
        if ($connection->access_token
            && $connection->expires_at
            && $connection->expires_at->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        if (! $connection->refresh_token) {
            return null;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google token refresh failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            // A revoked/expired refresh token means the connection is dead.
            if (in_array($response->status(), [400, 401], true)) {
                $connection->update(['disconnected_at' => now()]);
            }

            return null;
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $connection->access_token;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventPayload(DefenseAttempt $attempt, User $reviewer, ?string $committeeRole): array
    {
        $team = $attempt->team;
        $subject = $team->subject;
        $roundName = $this->roundName($attempt);

        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            $attempt->defense_date->format('Y-m-d').' '.substr((string) $attempt->defense_time, 0, 5),
            config('app.timezone'),
        );
        $end = $start->copy()->addMinutes((int) ($attempt->defense_duration ?: 60));

        $students = $team->members
            ->where('id', '!=', $reviewer->id)
            ->pluck('name')
            ->filter()
            ->implode(', ');

        $appUrl = rtrim((string) config('app.url'), '/');
        $paper = $attempt->papers->first();

        $descriptionLines = array_filter([
            'Subject: '.$subject->title,
            'Team: '.$team->name,
            $students !== '' ? 'Students: '.$students : null,
            'Defense round: '.$roundName,
            'Date & time: '.$start->format('D, d M Y, g:i A').' – '.$end->format('g:i A'),
            'Room: '.($attempt->defense_room ?: 'To be announced'),
            $committeeRole ? 'Your role: '.$this->humanRole($committeeRole) : null,
            '',
            'Open team room: '.$appUrl.'/assigned-teams',
            $paper ? 'Document / rubric: '.$appUrl.'/papers/'.$paper->id : null,
            '',
            'Scormetry remains the official source of defense schedules. This Google Calendar event is a convenience copy of your approved defense session.',
        ], fn ($line) => $line !== null);

        return [
            'summary' => 'FYP Defense: Team '.$team->name.' - '.$roundName,
            'location' => $attempt->defense_room ?: 'To be announced',
            'description' => implode("\n", $descriptionLines),
            'start' => [
                'dateTime' => $start->copy()->utc()->toRfc3339String(),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $end->copy()->utc()->toRfc3339String(),
                'timeZone' => 'UTC',
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 24 * 60],
                ],
            ],
        ];
    }

    private function roundName(DefenseAttempt $attempt): string
    {
        $period = $attempt->period?->name ?: 'Defense';

        if ($attempt->attempt_type === 're_defense' || $attempt->attempt_number > 1) {
            return $period.' (Re-defense)';
        }

        return $period;
    }

    private function humanRole(string $role): string
    {
        return match ($role) {
            'technical_examiner' => 'Technical Examiner',
            'academic_examiner' => 'Academic Examiner',
            'advisor' => 'Advisor',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }
}
