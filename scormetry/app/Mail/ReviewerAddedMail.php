<?php

namespace App\Mail;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewerAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $reviewer,
        public readonly Subject $reviewerSubject,
        public readonly string $committeeRole,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been added as a reviewer — '.$this->reviewerSubject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reviewer-added',
        );
    }
}
