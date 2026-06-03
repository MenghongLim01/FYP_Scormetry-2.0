<?php

namespace App\Mail;

use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamAdvisorInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $advisor,
        public readonly Team $team,
        public readonly Subject $forSubject,
        public readonly string $invitedByName,
        public readonly bool $pendingApproval,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited as a team advisor — '.$this->forSubject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-advisor-invite',
        );
    }
}
