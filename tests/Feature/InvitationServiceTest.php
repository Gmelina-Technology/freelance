<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $owner = User::factory()->create();
    $this->account = Account::factory()->for($owner, 'owner')->create();
    $this->service = app(InvitationService::class);
});

function pendingInvitation(Account $account, string $email): Invitation
{
    return Invitation::create([
        'account_id' => $account->id,
        'email' => $email,
        'role' => AccountRole::Member,
        'invited_by_id' => $account->owner_id,
    ]);
}

it('refuses to accept an invitation for a user with a different email', function () {
    $invitation = pendingInvitation($this->account, 'invited@example.com');
    $someoneElse = User::factory()->create();

    expect(fn () => $this->service->acceptForUser($invitation, $someoneElse))
        ->toThrow(AuthorizationException::class);

    expect($invitation->fresh()->isPending())->toBeTrue()
        ->and($someoneElse->getAccountRole($this->account))->toBeNull();
});

it('returns false and attaches nothing when the invitation was already accepted', function () {
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $invitation = pendingInvitation($this->account, 'invited@example.com');
    $invitation->accept();

    expect($this->service->acceptForUser($invitation, $user))->toBeFalse()
        ->and($user->getAccountRole($this->account))->toBeNull();
});

it('does not create a user or send a link for an already accepted invitation', function () {
    Notification::fake();
    $invitation = pendingInvitation($this->account, 'accepted@example.com');
    $invitation->accept();
    $usersBefore = User::count();

    expect(fn () => $this->service->acceptAsNewUser($invitation))->toThrow(DomainException::class);

    expect(User::count())->toBe($usersBefore);
    Notification::assertNothingSent();
});

it('does not create a user or send a link when the email already has a user', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = pendingInvitation($this->account, 'existing@example.com');
    $usersBefore = User::count();

    expect(fn () => $this->service->acceptAsNewUser($invitation))->toThrow(DomainException::class);

    expect(User::count())->toBe($usersBefore)
        ->and($invitation->fresh()->isPending())->toBeTrue();
    Notification::assertNotSentTo($existing, ResetPassword::class);
});
