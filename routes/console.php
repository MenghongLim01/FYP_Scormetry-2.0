<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Defense reminders — the command checks T-24h and T-1h windows, so run it
// frequently enough to catch them. Requires `php artisan schedule:work` (dev)
// or a system cron calling `schedule:run` every minute (production).
Schedule::command('defenses:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('reviews:process-deadlines')->everyTenMinutes()->withoutOverlapping();
