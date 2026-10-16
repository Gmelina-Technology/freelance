<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

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
                    $this->invitation->expires_at ?? now()->addDays(config('invitations.expiry_days')),
                    ['token' => $this->invitation->token],
                ),
            ],
        );
    }

    /**
     * Handle a queued email's failure. Logs ids only, never the invitee's address.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Failed to send invitation email', [
            'invitation_id' => $this->invitation->getKey(),
            'account_id' => $this->invitation->account_id,
            'exception' => $exception::class,
        ]);
    }
}
