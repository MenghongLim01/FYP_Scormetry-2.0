<?php

namespace App\Console\Commands;

use App\Mail\DefenseReminderMail;
use App\Models\DefenseAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('defenses:send-reminders')]
#[Description('Send T-24h and T-1h defense reminders to assigned judges and student team members.')]
class SendDefenseReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;
        $now = CarbonImmutable::now();

        DefenseAttempt::query()
            ->whereNotNull('defense_date')
            ->whereNotNull('defense_time')
            ->with(['team.members', 'activeReviewerAssignments.reviewer'])
            ->chunkById(50, function ($attempts) use ($now, &$sent): void {
                foreach ($attempts as $attempt) {
                    $defenseAt = CarbonImmutable::parse($attempt->defense_date->format('Y-m-d').' '.$attempt->defense_time);
                    $minutesUntilDefense = $now->diffInMinutes($defenseAt, false);

                    if ($attempt->reminder_24h_sent_at === null && $minutesUntilDefense >= 1430 && $minutesUntilDefense <= 1450) {
                        $this->sendReminder($attempt, '24-hour');
                        $attempt->forceFill(['reminder_24h_sent_at' => now()])->save();
                        $sent++;
                    }

                    if ($attempt->reminder_1h_sent_at === null && $minutesUntilDefense >= 50 && $minutesUntilDefense <= 70) {
                        $this->sendReminder($attempt, '1-hour');
                        $attempt->forceFill(['reminder_1h_sent_at' => now()])->save();
                        $sent++;
                    }
                }
            });

        $this->info("Defense reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function sendReminder(DefenseAttempt $attempt, string $label): void
    {
        $team = $attempt->team;
        $recipients = $team->members
            ->merge($attempt->activeReviewerAssignments->pluck('reviewer'))
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new DefenseReminderMail($team, $label));
        }
    }
}
