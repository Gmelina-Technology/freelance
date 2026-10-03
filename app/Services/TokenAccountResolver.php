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
     * Return the account the user's current token is bound to. The user must still own
     * or belong to that account.
     *
     * @throws AuthorizationException
     */
    public function resolve(User $user): Account
    {
        $token = $user->currentAccessToken();

        $boundAccountId = $token instanceof PersonalAccessToken ? $token->account_id : null;

        if ($boundAccountId === null) {
            throw new AuthorizationException('This token is not bound to an account; create a new token on the API Tokens page.');
        }

        $account = $user->accounts()->whereKey($boundAccountId)->first()
            ?? $user->ownedAccounts()->whereKey($boundAccountId)->first();

        return $account ?? throw new AuthorizationException('You do not have access to that account.');
    }
}
