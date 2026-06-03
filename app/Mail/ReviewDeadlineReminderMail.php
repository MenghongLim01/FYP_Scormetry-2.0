<?php

namespace App\Mail;

use App\Models\DefenseAttemptReviewer;
use App\Models\Paper;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewDeadlineReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DefenseAttemptReviewer $assignment,
        public readonly Paper $paper,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review Overdue — '.$this->paper->team->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-deadline-reminder',
        );
    }
}
