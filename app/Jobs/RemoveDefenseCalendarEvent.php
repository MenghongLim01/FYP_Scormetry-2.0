<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveDefenseCalendarEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * The Google event id is passed directly so removal still works even after
     * the defense attempt (and its tracking row) has been deleted.
     */
    public function __construct(
        public int $reviewerId,
        public string $googleEventId,
    ) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        $reviewer = User::with('googleCalendarConnection')->find($this->reviewerId);

        if (! $reviewer) {
            return;
        }

        $calendar->removeEvent($reviewer, $this->googleEventId);
    }
}
