<?php

namespace App\Mail;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Review $review) {}

    public function envelope(): Envelope
    {
        $prefix = $this->review->auto_submitted_at ? 'Review Auto-submitted' : 'Review Completed';

        return new Envelope(
            subject: $prefix.' — '.$this->review->paper->subject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-completed',
        );
    }
}
