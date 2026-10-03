<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Works out which account an API/MCP request acts on. Every personal access token is
 * bound to exactly one account, so a leaked token never exposes the user's other accounts.
 */
class TokenAccountResolver
{
    /**
     * Return the account the user's current token is bound to. An `account_id` in the
     * request is only accepted when it equals the bound account, and the user must still
     * own or belong to that account.
     *
     * @throws AuthorizationException
     */
    public function resolve(User $user, mixed $requestedAccountId = null): Account
    {
        $token = $user->currentAccessToken();

        $boundAccountId = $token instanceof PersonalAccessToken ? $token->account_id : null;

        if ($boundAccountId === null) {
            throw new AuthorizationException('This token is not bound to an account; create a new token on the API Tokens page.');
        }

        if ($requestedAccountId !== null && (string) $requestedAccountId !== (string) $boundAccountId) {
            throw new AuthorizationException('This token is bound to a different account.');
        }

        $account = $user->accounts()->whereKey($boundAccountId)->first()
            ?? $user->ownedAccounts()->whereKey($boundAccountId)->first();

        return $account ?? throw new AuthorizationException('You do not have access to that account.');
    }
}
