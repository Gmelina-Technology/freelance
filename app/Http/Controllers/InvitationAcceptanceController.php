<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvitationAcceptanceController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function accept(Request $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->invalidInvitation();
        }

        $invitation = Invitation::where('token', $token)->first();

        if ($invitation === null || ($invitation->isPending() && $invitation->isExpired())) {
            return $this->invalidInvitation();
        }

        $user = $request->user();

        if (! $user) {
            return $this->acceptForGuest($invitation);
        }

        if ($user->email !== $invitation->email) {
            abort(403);
        }

        return $this->acceptForUser($invitation, $user);
    }

    /**
     * One outcome for every link that cannot be used (bad or expired signature, unknown or revoked
     * token, passed expiry), so a visitor learns nothing about which invitations exist.
     */
    private function invalidInvitation(): RedirectResponse
    {
        Notification::make()
            ->warning()
            ->title('Invitation expired or no longer valid')
            ->body('Ask the account owner or manager to send you a new invitation.')
            ->send();

        return redirect()->route('filament.app.auth.login');
    }

    /**
     * A logged-out visitor is never logged in by the link. A brand-new invitee gets an
     * account with an unknown password and a reset email; anyone else has to log in first.
     */
    private function acceptForGuest(Invitation $invitation): RedirectResponse
    {
        if ($invitation->isAccepted()) {
            Notification::make()
                ->warning()
                ->title('Invitation already accepted')
                ->body('This invitation has already been accepted. Please log in.')
                ->send();

            return redirect()->route('filament.app.auth.login');
        }

        if (User::where('email', $invitation->email)->exists()) {
            Notification::make()
                ->info()
                ->title('Log in to accept the invitation')
                ->body('Log in with your account to join the invited account.')
                ->send();

            return redirect()->guest(route('filament.app.auth.login'));
        }

        $this->invitations->acceptAsNewUser($invitation);

        Notification::make()
            ->success()
            ->title('Invitation accepted')
            ->body('Check your email for a link to set your password, then log in.')
            ->send();

        return redirect()->route('filament.app.auth.login');
    }

    private function acceptForUser(Invitation $invitation, User $user): RedirectResponse
    {
        if ($invitation->isAccepted()) {
            Notification::make()
                ->warning()
                ->title('Invitation already accepted')
                ->body('This invitation has already been accepted.')
                ->send();

            return redirect()->route('filament.app.pages.dashboard', $invitation->account);
        }

        $this->invitations->acceptForUser($invitation, $user);

        Notification::make()
            ->success()
            ->title('Invitation accepted')
            ->body('You have been added to the account.')
            ->send();

        return redirect()->route('filament.app.pages.dashboard', $invitation->account);
    }
}
