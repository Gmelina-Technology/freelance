<?php

namespace App\Http\Middleware;

use App\Services\TokenAccountResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindApiAccount
{
    public function __construct(private TokenAccountResolver $resolver) {}

    /**
     * Bind the account the authenticated (Sanctum) token is tied to for downstream
     * controllers/requests. Runs after `auth:sanctum`. Tokens without a bound account
     * are refused, and an `account_id` (query or body) must match the bound one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $account = $this->resolver->resolve($request->user(), $request->input('account_id'));
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }

        $request->attributes->set('account', $account);

        return $next($request);
    }
}
