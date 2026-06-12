<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindApiAccount
{
    /**
     * Resolve the account for the authenticated (Sanctum) user and bind it for
     * downstream controllers/requests. Runs after `auth:sanctum`.
     *
     * Account selection: an optional `account_id` (query or body) lets the user
     * pick among accounts they belong to; otherwise the first owned account, then
     * the first account they're a member of, is used.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $requested = $request->input('account_id');

        if ($requested !== null) {
            $account = $user->accounts()->whereKey($requested)->first()
                ?? $user->ownedAccounts()->whereKey($requested)->first();

            if (! $account) {
                abort(403, 'You do not have access to that account.');
            }
        } else {
            $account = $user->ownedAccounts()->first() ?? $user->accounts()->first();

            if (! $account) {
                abort(403, 'This user is not attached to any account.');
            }
        }

        $request->attributes->set('account', $account);

        return $next($request);
    }
}
