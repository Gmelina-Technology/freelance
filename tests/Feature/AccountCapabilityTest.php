<?php

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use App\Filament\App\Resources\Clients\ClientResource;
use App\Filament\App\Resources\Clients\Pages\ListClients;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Filament\App\Resources\Sales\SaleResource;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Build a user in one of the role situations the shared rule has to handle.
 *
 * @return array{0: User, 1: Account, 2: ?AccountRole}
 */
function userWithRoleSituation(string $situation): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();

    return match ($situation) {
        'pivot owner' => (function () use ($account, $owner) {
            $account->users()->attach($owner, ['role' => AccountRole::Owner->value]);

            return [$owner, $account, AccountRole::Owner];
        })(),
        'owner_id only' => [$owner, $account, AccountRole::Owner],
        'owner_id with member pivot' => (function () use ($account, $owner) {
            $account->users()->attach($owner, ['role' => AccountRole::Member->value]);

            return [$owner, $account, AccountRole::Owner];
        })(),
        'manager' => [makeAccountUser($account, AccountRole::Manager), $account, AccountRole::Manager],
        'member' => [makeAccountUser($account, AccountRole::Member), $account, AccountRole::Member],
        'non-member' => [User::factory()->create(), $account, null],
    };
}

dataset('role situations', [
    'pivot owner',
    'owner_id only',
    'owner_id with member pivot',
    'manager',
    'member',
    'non-member',
]);

it('resolves one role for the user and the account', function (string $situation) {
    [$user, $account, $expected] = userWithRoleSituation($situation);

    expect($user->getAccountRole($account))->toBe($expected)
        ->and($account->getUserRole($user))->toBe($expected);
})->with('role situations');

it('grants each capability to exactly the roles in the map', function (string $situation) {
    [$user, $account, $role] = userWithRoleSituation($situation);

    $ownerOrManager = in_array($role, [AccountRole::Owner, AccountRole::Manager], true);

    foreach (AccountCapability::cases() as $capability) {
        $expected = $capability === AccountCapability::ManageClients
            ? $role === AccountRole::Owner
            : $ownerOrManager;

        expect(Gate::forUser($user)->allows($capability, $account))->toBe($expected, "{$capability->value} for {$situation}")
            ->and($capability->isGrantedTo($role))->toBe($expected);
    }
})->with('role situations');

it('describes the roles that may run a capability', function () {
    expect(AccountCapability::ManageInvoices->describeAllowedRoles())->toBe('owner or manager')
        ->and(AccountCapability::ManageClients->describeAllowedRoles())->toBe('owner')
        ->and(AccountCapability::ManageInvoices->isGrantedTo(null))->toBeFalse();
});

it('shows invoices, quotes and sales to owners and managers', function (string $situation) {
    [$user, $account] = userWithRoleSituation($situation);
    bindFilamentTenant($user, $account);

    expect(InvoiceResource::canAccess())->toBeTrue()
        ->and(QuoteResource::canAccess())->toBeTrue()
        ->and(SaleResource::canAccess())->toBeTrue();
})->with(['pivot owner', 'owner_id only', 'owner_id with member pivot', 'manager']);

it('hides invoices, quotes and sales from members', function () {
    [$user, $account] = userWithRoleSituation('member');
    bindFilamentTenant($user, $account);

    expect(InvoiceResource::canAccess())->toBeFalse()
        ->and(QuoteResource::canAccess())->toBeFalse()
        ->and(SaleResource::canAccess())->toBeFalse();
});

it('keeps clients owner only', function (string $situation, bool $allowed) {
    [$user, $account] = userWithRoleSituation($situation);
    bindFilamentTenant($user, $account);

    expect(ClientResource::canAccess())->toBe($allowed)
        ->and(ListClients::canAccess())->toBe($allowed);
})->with([
    ['pivot owner', true],
    ['owner_id only', true],
    ['owner_id with member pivot', true],
    ['manager', false],
    ['member', false],
]);
