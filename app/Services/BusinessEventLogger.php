<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BusinessEventLogger
{
    /**
     * Write one business event to the dedicated log channel. Context carries ids only, never token
     * plaintext or email addresses. user_id is null for guests and background work.
     *
     * @param  array<string, int|string|bool|null>  $context
     */
    public function record(string $event, int $accountId, array $context = []): void
    {
        Log::channel('business')->info($event, [
            'account_id' => $accountId,
            'user_id' => Auth::id(),
            ...$context,
        ]);
    }
}
