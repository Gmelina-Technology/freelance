<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InvitationAcceptanceController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

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

        $invitedUser = DB::transaction(function () use ($invitation): User {
            $invitedUser = new User([
                'name' => Str::title(Str::before($invitation->email, '@')),
                'email' => $invitation->email,
                'password' => Str::password(64),
            ]);
            $invitedUser->forceFill(['email_verified_at' => now()])->save();

            $this->completeAcceptance($invitation, $invitedUser);

            return $invitedUser;
        });

        $this->sendPasswordSetupLink($invitedUser);

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

        $this->completeAcceptance($invitation, $user);

        Notification::make()
            ->success()
            ->title('Invitation accepted')
            ->body('You have been added to the account.')
            ->send();

        return redirect()->route('filament.app.pages.dashboard', $invitation->account);
    }

    private function completeAcceptance(Invitation $invitation, User $user): void
    {
        $invitation->accept();

        $account = $invitation->account;

        if (! $account->users()->where('user_id', $user->getKey())->exists()) {
            $account->users()->attach($user->getKey(), [
                'role' => $invitation->role,
            ]);
        }
    }

    /**
     * Email the standard Filament reset-password link so the invitee chooses their own password.
     */
    private function sendPasswordSetupLink(User $user): void
    {
        $panel = Filament::getPanel('app');
        $token = Password::broker($panel->getAuthPasswordBroker())->createToken($user);

        $notification = new ResetPassword($token);
        $notification->url = $panel->getResetPasswordUrl($token, $user);

        $user->notify($notification);
    }
}
