<?php

namespace App\Jobs;

use App\Models\DefenseAttempt;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncDefenseCalendarEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $defenseAttemptId,
        public int $reviewerId,
        public ?string $committeeRole = null,
    ) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        $attempt = DefenseAttempt::with('team.subject', 'team.members', 'period', 'papers')
            ->find($this->defenseAttemptId);
        $reviewer = User::with('googleCalendarConnection')->find($this->reviewerId);

        if (! $attempt || ! $reviewer) {
            return;
        }

        $calendar->syncEvent($attempt, $reviewer, $this->committeeRole);
    }
}
