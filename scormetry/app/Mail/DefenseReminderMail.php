<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DefenseReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Team $team,
        public readonly string $reminderLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminderLabel.' Defense Reminder — '.$this->team->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.defense-reminder',
        );
    }
}
