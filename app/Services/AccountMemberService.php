<?php

namespace App\Services;

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class AccountMemberService
{
    /**
     * Change the role of an existing member. Only owners may grant the owner role or change an owner.
     *
     * @throws AuthorizationException the actor lacks the capability for this role change on the account
     * @throws DomainException the target is the account's owner_id user, or is not a member of the account
     */
    public function changeRole(Account $account, User $actor, User $member, AccountRole $role): void
    {
        Gate::forUser($actor)->authorize(AccountCapability::ManageMembers, $account);

        if ((int) $account->owner_id === (int) $member->getKey()) {
            throw new DomainException('The account owner\'s role cannot be changed.');
        }

        $current = $member->load('accounts')->getAccountRole($account);

        if ($current === null) {
            throw new DomainException('This user is not a member of the account.');
        }

        Gate::forUser($actor)->authorize(AccountCapability::forRoleChange($current, $role), $account);

        $member->accounts()->updateExistingPivot($account->getKey(), ['role' => $role->value]);
    }
}
