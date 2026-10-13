<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use DomainException;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * Accept an invitation for the user it was sent to and attach them to its account with the invited role.
     * Returns false, and attaches nothing, when the invitation was already accepted.
     *
     * @throws AuthorizationException
     */
    public function acceptForUser(Invitation $invitation, User $user): bool
    {
        if ($user->email !== $invitation->email) {
            throw new AuthorizationException('This invitation was sent to a different email address.');
        }

        if ($invitation->isAccepted()) {
            return false;
        }

        $invitation->accept();

        $account = $invitation->account;

        if (! $account->users()->where('user_id', $user->getKey())->exists()) {
            $account->users()->attach($user->getKey(), [
                'role' => $invitation->role,
            ]);
        }

        return true;
    }

    /**
     * Create the invitee's user with an unknown password and a verified email, accept the invitation
     * for them, then email the reset-password link once the transaction has committed.
     *
     * @throws DomainException
     */
    public function acceptAsNewUser(Invitation $invitation): User
    {
        if ($invitation->isAccepted()) {
            throw new DomainException('This invitation has already been accepted.');
        }

        if (User::where('email', $invitation->email)->exists()) {
            throw new DomainException('A user with this email already exists.');
        }

        $user = DB::transaction(function () use ($invitation): User {
            $user = new User([
                'name' => Str::title(Str::before($invitation->email, '@')),
                'email' => $invitation->email,
                'password' => Str::password(64),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->acceptForUser($invitation, $user);

            return $user;
        });

        $this->sendPasswordSetupLink($user);

        return $user;
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
