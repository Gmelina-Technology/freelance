<?php

namespace App\Services;

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use App\Mail\InvitationEmail;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use DomainException;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private BusinessEventLogger $events) {}

    /**
     * Create a pending invitation and queue its email. Only owners may invite someone as an owner.
     *
     * @throws AuthorizationException
     */
    public function invite(Account $account, User $actor, string $email, AccountRole $role): Invitation
    {
        Gate::forUser($actor)->authorize(AccountCapability::forRoleChange(null, $role), $account);

        $invitation = Invitation::create([
            'account_id' => $account->getKey(),
            'email' => $email,
            'role' => $role,
            'invited_by_id' => $actor->getKey(),
        ]);

        Mail::to($invitation->email)->send(new InvitationEmail($invitation));

        return $invitation;
    }

    /**
     * Re-issue a pending invitation with a new token and expiry, and email the new link. Links from
     * earlier emails stop working.
     *
     * @throws AuthorizationException
     * @throws DomainException the invitation belongs to another account or was already accepted
     */
    public function resend(Account $account, User $actor, Invitation $invitation): Invitation
    {
        if ((int) $invitation->account_id !== (int) $account->getKey()) {
            throw new DomainException('This invitation belongs to a different account.');
        }

        if ($invitation->isAccepted()) {
            throw new DomainException('This invitation has already been accepted.');
        }

        Gate::forUser($actor)->authorize(AccountCapability::forRoleChange(null, $invitation->role), $account);

        $invitation->forceFill([
            'token' => Str::random(40),
            'expires_at' => now()->addDays(config('invitations.expiry_days')),
        ])->save();

        Mail::to($invitation->email)->send(new InvitationEmail($invitation));

        return $invitation;
    }

    /**
     * Accept an invitation for the user it was sent to and attach them to its account with the invited role.
     * Returns false, and attaches nothing, when the invitation was already accepted.
     *
     * @throws AuthorizationException
     * @throws DomainException the pending invitation has expired
     */
    public function acceptForUser(Invitation $invitation, User $user): bool
    {
        if ($user->email !== $invitation->email) {
            throw new AuthorizationException('This invitation was sent to a different email address.');
        }

        if ($invitation->isAccepted()) {
            return false;
        }

        if ($invitation->isExpired()) {
            throw new DomainException('This invitation has expired.');
        }

        $invitation->accept();

        $account = $invitation->account;

        if (! $account->users()->where('user_id', $user->getKey())->exists()) {
            $account->users()->attach($user->getKey(), [
                'role' => $invitation->role,
            ]);
        }

        $this->events->record('invitation.accepted', $invitation->account_id, [
            'invitation_id' => $invitation->getKey(),
            'role' => $invitation->role->value,
            'member_user_id' => $user->getKey(),
        ]);

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

        if ($invitation->isExpired()) {
            throw new DomainException('This invitation has expired.');
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
