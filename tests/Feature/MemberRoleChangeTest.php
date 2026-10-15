<?php

use App\Enums\AccountRole;
use App\Filament\App\Clusters\Settings\Resources\Members\Pages\ManageMembers;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountMemberService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The role stored on the account_user pivot, read fresh from the database.
 */
function pivotRole(Account $account, User $user): ?string
{
    return $account->users()->whereKey($user->getKey())->first()?->pivot->role->value;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->account->users()->attach($this->owner, ['role' => AccountRole::Owner->value]);

    $this->manager = makeAccountUser($this->account, AccountRole::Manager);
    $this->member = makeAccountUser($this->account, AccountRole::Member);
    $this->secondOwner = makeAccountUser($this->account, AccountRole::Owner);
    $this->service = app(AccountMemberService::class);
});

it('lets an owner promote a member to owner', function () {
    $this->service->changeRole($this->account, $this->owner, $this->member, AccountRole::Owner);

    expect(pivotRole($this->account, $this->member))->toBe('owner');
});

it('lets an owner demote another owner', function () {
    $this->service->changeRole($this->account, $this->owner, $this->secondOwner, AccountRole::Manager);

    expect(pivotRole($this->account, $this->secondOwner))->toBe('manager');
});

it('lets a manager move a member to manager', function () {
    $this->service->changeRole($this->account, $this->manager, $this->member, AccountRole::Manager);

    expect(pivotRole($this->account, $this->member))->toBe('manager');
});

it('refuses a manager who tries to make someone an owner and changes nothing', function (string $target) {
    $user = $this->{$target};

    expect(fn () => $this->service->changeRole($this->account, $this->manager, $user, AccountRole::Owner))
        ->toThrow(AuthorizationException::class);

    expect(pivotRole($this->account, $user))->toBe($target === 'manager' ? 'manager' : 'member');
})->with(['member', 'manager']);

it('refuses a manager who tries to change an owner and changes nothing', function (AccountRole $newRole) {
    expect(fn () => $this->service->changeRole($this->account, $this->manager, $this->secondOwner, $newRole))
        ->toThrow(AuthorizationException::class);

    expect(pivotRole($this->account, $this->secondOwner))->toBe('owner');
})->with([AccountRole::Manager, AccountRole::Member]);

it('refuses a member who calls the service', function () {
    $other = makeAccountUser($this->account, AccountRole::Member);

    expect(fn () => $this->service->changeRole($this->account, $this->member, $other, AccountRole::Manager))
        ->toThrow(AuthorizationException::class);

    expect(pivotRole($this->account, $other))->toBe('member');
});

it('refuses to change the role of the account owner_id user', function () {
    expect(fn () => $this->service->changeRole($this->account, $this->owner, $this->owner, AccountRole::Member))
        ->toThrow(DomainException::class);

    expect(pivotRole($this->account, $this->owner))->toBe('owner');
});

it('refuses a user who is not a member of the account and adds no pivot row', function () {
    $outsider = User::factory()->create();

    expect(fn () => $this->service->changeRole($this->account, $this->owner, $outsider, AccountRole::Member))
        ->toThrow(DomainException::class, 'not a member');

    expect($this->account->users()->whereKey($outsider->getKey())->exists())->toBeFalse();
});

it('changes a role from the members table', function () {
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ManageMembers::class)
        ->callTableAction('changeRole', $this->member, ['role' => 'owner'])
        ->assertHasNoTableActionErrors();

    expect(pivotRole($this->account, $this->member))->toBe('owner');
});

it('lets a manager change a member role from the table', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ManageMembers::class)
        ->callTableAction('changeRole', $this->member, ['role' => 'manager'])
        ->assertHasNoTableActionErrors();

    expect(pivotRole($this->account, $this->member))->toBe('manager');
});

it('does not offer the owner role to a manager', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ManageMembers::class)
        ->mountTableAction('changeRole', $this->member)
        ->assertFormFieldExists('role', fn ($field): bool => array_keys($field->getOptions()) === ['manager', 'member']);
});

it('refuses the owner role from a manager when it is submitted anyway', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ManageMembers::class)
        ->callTableAction('changeRole', $this->member, ['role' => 'owner'])
        ->assertHasTableActionErrors(['role']);

    expect(pivotRole($this->account, $this->member))->toBe('member');
});

it('offers the owner role to an owner', function () {
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ManageMembers::class)
        ->mountTableAction('changeRole', $this->member)
        ->assertFormFieldExists('role', fn ($field): bool => array_keys($field->getOptions()) === ['owner', 'manager', 'member']);
});

it('hides the change role action on owner rows from a manager', function () {
    bindFilamentTenant($this->manager, $this->account);

    Livewire::test(ManageMembers::class)
        ->assertTableActionHidden('changeRole', $this->secondOwner)
        ->assertTableActionVisible('changeRole', $this->member);
});

it('hides the change role action on the account owner row from everyone', function (string $actor) {
    bindFilamentTenant($this->{$actor}, $this->account);

    Livewire::test(ManageMembers::class)
        ->assertTableActionHidden('changeRole', $this->owner);
})->with(['owner', 'manager', 'secondOwner']);

it('does not run the action on an owner row for a manager', function () {
    bindFilamentTenant($this->manager, $this->account);

    try {
        Livewire::test(ManageMembers::class)
            ->call('mountTableAction', 'changeRole', (string) $this->secondOwner->getKey())
            ->call('callMountedTableAction');
    } catch (Throwable) {
        // Filament refuses an action the user is not authorized to run.
    }

    expect(pivotRole($this->account, $this->secondOwner))->toBe('owner');
});
