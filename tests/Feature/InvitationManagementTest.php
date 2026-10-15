<?php

use App\Enums\AccountRole;
use App\Filament\App\Clusters\Settings\Resources\Invitations\Pages\ListInvitations;
use App\Mail\InvitationEmail;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->manager = makeAccountUser($this->account, AccountRole::Manager);
    $this->member = makeAccountUser($this->account, AccountRole::Member);
    $this->service = app(InvitationService::class);
});

it('lets an owner invite someone as an owner', function () {
    $invitation = $this->service->invite($this->account, $this->owner, 'new@example.com', AccountRole::Owner);

    expect($invitation->role)->toBe(AccountRole::Owner)
        ->and($invitation->invited_by_id)->toBe($this->owner->id)
        ->and($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();

    Mail::assertQueued(InvitationEmail::class, fn (InvitationEmail $mail): bool => $mail->invitation->is($invitation));
});

it('lets a manager invite someone as a member', function () {
    $invitation = $this->service->invite($this->account, $this->manager, 'new@example.com', AccountRole::Member);

    expect($invitation->exists)->toBeTrue();
    Mail::assertQueued(InvitationEmail::class, 1);
});

it('refuses a manager who invites someone as an owner, creating no row and no mail', function () {
    expect(fn () => $this->service->invite($this->account, $this->manager, 'new@example.com', AccountRole::Owner))
        ->toThrow(AuthorizationException::class);

    expect(Invitation::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('refuses a member who invites anyone', function () {
    expect(fn () => $this->service->invite($this->account, $this->member, 'new@example.com', AccountRole::Member))
        ->toThrow(AuthorizationException::class);

    expect(Invitation::count())->toBe(0);
});

it('creates an invitation from the invitations page and queues the email once', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ListInvitations::class)
        ->callAction('create', ['email' => 'page@example.com', 'role' => 'member'])
        ->assertHasNoActionErrors();

    $invitation = Invitation::where('email', 'page@example.com')->sole();

    expect($invitation->account_id)->toBe($this->account->id)
        ->and($invitation->invited_by_id)->toBe($this->manager->id);
    Mail::assertQueued(InvitationEmail::class, 1);
});

it('does not offer the owner role to a manager on the invitations page', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ListInvitations::class)
        ->mountAction('create')
        ->assertFormFieldExists('role', fn ($field): bool => array_keys($field->getOptions()) === ['manager', 'member']);
});

it('refuses the owner role from a manager on the invitations page when it is submitted anyway', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ListInvitations::class)
        ->callAction('create', ['email' => 'page@example.com', 'role' => 'owner'])
        ->assertHasActionErrors(['role']);

    expect(Invitation::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('offers the owner role to an owner on the invitations page', function () {
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ListInvitations::class)
        ->mountAction('create')
        ->assertFormFieldExists('role', fn ($field): bool => array_keys($field->getOptions()) === ['owner', 'manager', 'member']);
});

it('re-issues a pending invitation with a new token and expiry and the same row', function (string $actor, AccountRole $role, bool $expired) {
    $invitation = Invitation::factory()->for($this->account)->role($role)->create();
    $expired && $invitation->forceFill(['expires_at' => now()->subDay()])->save();
    $oldToken = $invitation->token;

    $resent = $this->service->resend($this->account, $this->{$actor}, $invitation);

    expect($resent->id)->toBe($invitation->id)
        ->and($resent->token)->not->toBe($oldToken)->toHaveLength(40)
        ->and($resent->fresh()->isExpired())->toBeFalse()
        ->and($resent->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();

    Mail::assertQueued(InvitationEmail::class, 1);
})->with([
    'owner resends an owner invitation' => ['owner', AccountRole::Owner, false],
    'manager resends a member invitation' => ['manager', AccountRole::Member, false],
    'manager resends an expired member invitation' => ['manager', AccountRole::Member, true],
]);

it('stops the old link working after a resend', function () {
    $invitation = Invitation::factory()->for($this->account)->create();
    $oldUrl = URL::temporarySignedRoute('invitations.accept', now()->addDays(7), ['token' => $invitation->token]);

    $this->service->resend($this->account, $this->owner, $invitation);

    $this->get($oldUrl)->assertRedirect(route('filament.app.auth.login'));

    expect($invitation->fresh()->isPending())->toBeTrue();
});

it('refuses a manager who resends an owner invitation and leaves it untouched', function () {
    $invitation = Invitation::factory()->for($this->account)->role(AccountRole::Owner)->create();
    $before = $invitation->only(['token', 'expires_at']);

    expect(fn () => $this->service->resend($this->account, $this->manager, $invitation))
        ->toThrow(AuthorizationException::class);

    expect($invitation->fresh()->only(['token', 'expires_at']))->toEqual($before);
    Mail::assertNothingQueued();
});

it('refuses to resend an accepted invitation or one from another account', function () {
    $accepted = Invitation::factory()->for($this->account)->accepted()->create();
    $foreign = Invitation::factory()->create();

    expect(fn () => $this->service->resend($this->account, $this->owner, $accepted))->toThrow(DomainException::class)
        ->and(fn () => $this->service->resend($this->account, $this->owner, $foreign))->toThrow(DomainException::class);

    Mail::assertNothingQueued();
});

it('resends from the invitations table', function () {
    $invitation = Invitation::factory()->for($this->account)->create();
    $oldToken = $invitation->token;
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ListInvitations::class)
        ->callTableAction('resend', $invitation)
        ->assertHasNoTableActionErrors();

    expect($invitation->fresh()->token)->not->toBe($oldToken);
    Mail::assertQueued(InvitationEmail::class, 1);
});

it('hides resend for accepted invitations and for owner invitations from a manager', function () {
    $accepted = Invitation::factory()->for($this->account)->accepted()->create();
    $ownerInvite = Invitation::factory()->for($this->account)->role(AccountRole::Owner)->create();
    $memberInvite = Invitation::factory()->for($this->account)->create();
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ListInvitations::class)
        ->assertTableActionHidden('resend', $accepted)
        ->assertTableActionHidden('resend', $ownerInvite)
        ->assertTableActionVisible('resend', $memberInvite);
});

it('does not resend an owner invitation when a manager forces the table action', function () {
    $invitation = Invitation::factory()->for($this->account)->role(AccountRole::Owner)->create();
    $oldToken = $invitation->token;
    bindFilamentTenant($this->manager, $this->account);

    try {
        Livewire::test(ListInvitations::class)
            ->call('mountTableAction', 'resend', (string) $invitation->getKey())
            ->call('callMountedTableAction');
    } catch (Throwable) {
        // Filament refuses an action the user is not authorized to run.
    }

    expect($invitation->fresh()->token)->toBe($oldToken);
    Mail::assertNothingQueued();
});

it('shows the invitation status', function () {
    $pending = Invitation::factory()->for($this->account)->create();
    $expired = Invitation::factory()->for($this->account)->expired()->create();
    $accepted = Invitation::factory()->for($this->account)->accepted()->create();
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ListInvitations::class)
        ->assertTableColumnStateSet('status', 'Pending', $pending)
        ->assertTableColumnStateSet('status', 'Expired', $expired)
        ->assertTableColumnStateSet('status', 'Accepted', $accepted);
});
