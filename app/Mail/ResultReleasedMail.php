<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResultReleasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Team $team) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Defense Results Released — '.$this->team->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.result-released',
        );
    }
}
