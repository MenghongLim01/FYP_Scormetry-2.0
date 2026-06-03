<?php

namespace App\Mail;

use App\Models\SubjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SubjectInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited as a reviewer — '.$this->invitation->subject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reviewer-invitation',
        );
    }
}
