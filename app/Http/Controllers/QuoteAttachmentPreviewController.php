<?php

namespace App\Http\Controllers;

use App\Enums\AccountCapability;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QuoteAttachmentPreviewController extends Controller
{
    /**
     * Stream the quote's uploaded attachment inline, for owners and managers of the quote's account.
     */
    public function __invoke(Request $request, Quote $quote): Response
    {
        $user = $request->user();
        $account = $quote->account;

        abort_unless(
            $account !== null && $user->canAccessTenant($account) && $user->can(AccountCapability::ManageQuotes, $account),
            403,
        );

        abort_unless($quote->hasStoredAttachment(), 404);

        return Storage::disk(Quote::ATTACHMENT_DISK)->response(
            $quote->attachment_path,
            basename($quote->attachment_path),
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }
}
