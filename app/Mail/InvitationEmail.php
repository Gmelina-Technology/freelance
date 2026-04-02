<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class InvitationEmail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join an account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => URL::temporarySignedRoute(
                    'invitations.accept',
                    now()->addDays(7),
                    ['token' => $this->invitation->token],
                ),
            ],
        );
    }
}
