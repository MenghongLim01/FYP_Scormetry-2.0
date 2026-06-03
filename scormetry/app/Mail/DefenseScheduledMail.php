<?php

namespace App\Mail;

use App\Models\DefenseAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use DateTimeInterface;

class DefenseScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{defense_date?: string|null, defense_time?: string|null, defense_duration?: int|null, defense_room?: string|null, score_deadline_at?: string|null}|null  $calendarSchedule
     */
    public function __construct(
        public readonly DefenseAttempt $attempt,
        public readonly string $changeType = 'scheduled',
        public readonly ?array $calendarSchedule = null,
    ) {
        $this->attempt->loadMissing('team.subject', 'period');
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->changeType) {
            'cancelled' => 'Defense Schedule Cancelled - '.$this->attempt->team->name,
            'updated' => 'Defense Schedule Updated - '.$this->attempt->team->name,
            default => 'Defense Schedule Confirmed - '.$this->attempt->team->name,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.defense-scheduled',
            with: [
                'attempt' => $this->attempt,
                'team' => $this->attempt->team,
                'period' => $this->attempt->period,
                'changeType' => $this->changeType,
                'startsAt' => $this->startsAt(),
                'endsAt' => $this->endsAt(),
                'scoreDeadlineAt' => $this->scoreDeadlineAt(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $method = $this->changeType === 'cancelled' ? 'CANCEL' : 'REQUEST';

        return [
            Attachment::fromData(
                fn () => $this->calendarInvite($method),
                $this->calendarFilename(),
            )->withMime('text/calendar; charset=UTF-8; method='.$method),
        ];
    }

    private function calendarInvite(string $method): string
    {
        $startsAt = $this->startsAt() ?? Carbon::now(config('app.timezone'));
        $endsAt = $this->endsAt() ?? $startsAt->copy()->addMinutes(60);
        $subject = $this->attempt->team->subject;
        $periodName = $this->attempt->period?->name ?? 'Defense';
        $summary = 'Scormetry Defense: '.$this->attempt->team->name.' - '.$periodName;
        $description = implode('\n', array_filter([
            'Subject: '.$subject->title,
            'Team: '.$this->attempt->team->name,
            'Round: '.$periodName.' / '.$this->attempt->label,
            'Room: '.($this->scheduleValue('defense_room') ?: 'To be announced'),
            $this->scoreDeadlineAt()
                ? 'Score deadline: '.$this->scoreDeadlineAt()?->format('d M Y, g:i A')
                : null,
            'Scormetry remains the official source of defense schedules. Google Calendar is only a convenience through this invitation.',
        ]));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Scormetry//Defense Schedule//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.$this->calendarUid(),
            'SEQUENCE:'.$this->calendarSequence(),
            'DTSTAMP:'.$this->icsDateTime(Carbon::now('UTC')),
            'DTSTART:'.$this->icsDateTime($startsAt->copy()->utc()),
            'DTEND:'.$this->icsDateTime($endsAt->copy()->utc()),
            'SUMMARY:'.$this->escapeIcsText($summary),
            'DESCRIPTION:'.$this->escapeIcsText($description),
            'LOCATION:'.$this->escapeIcsText($this->scheduleValue('defense_room') ?: 'To be announced'),
            'STATUS:'.($this->changeType === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'TRANSP:OPAQUE',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return collect($lines)
            ->map(fn (string $line) => $this->foldIcsLine($line))
            ->implode("\r\n")."\r\n";
    }

    private function startsAt(): ?Carbon
    {
        $date = $this->scheduleValue('defense_date');

        if (! $date) {
            return null;
        }

        $time = substr((string) ($this->scheduleValue('defense_time') ?: '00:00'), 0, 5);

        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, config('app.timezone'));
    }

    private function endsAt(): ?Carbon
    {
        $startsAt = $this->startsAt();

        if (! $startsAt) {
            return null;
        }

        return $startsAt->copy()->addMinutes((int) ($this->scheduleValue('defense_duration') ?: 60));
    }

    private function scoreDeadlineAt(): ?Carbon
    {
        $value = $this->scheduleValue('score_deadline_at');

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }

    private function scheduleValue(string $key): mixed
    {
        if (array_key_exists($key, $this->calendarSchedule ?? [])) {
            return $this->calendarSchedule[$key];
        }

        $value = $this->attempt->{$key};

        if ($value instanceof DateTimeInterface) {
            return $key === 'defense_date'
                ? Carbon::instance($value)->format('Y-m-d')
                : Carbon::instance($value)->toISOString();
        }

        if ($key === 'defense_date' && $value) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        return $value;
    }

    private function calendarFilename(): string
    {
        return Str::slug($this->attempt->team->name.'-'.$this->attempt->period?->name.'-'.$this->attempt->label).'.ics';
    }

    private function calendarUid(): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'scormetry.local';

        return 'scormetry-defense-attempt-'.$this->attempt->id.'@'.$host;
    }

    private function calendarSequence(): int
    {
        return $this->attempt->updated_at?->timestamp ?? 0;
    }

    private function icsDateTime(Carbon $date): string
    {
        return $date->format('Ymd\THis\Z');
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\r\n", "\n", "\r"],
            ["\\\\", '\;', '\,', '\n', '\n', '\n'],
            $value,
        );
    }

    private function foldIcsLine(string $line): string
    {
        return trim(chunk_split($line, 73, "\r\n "));
    }
}
