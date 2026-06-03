<?php

namespace App\Mail;

use App\Models\Paper;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaperPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Paper $paper) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review Completed — '.$this->paper->subject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.paper-published',
        );
    }
}
