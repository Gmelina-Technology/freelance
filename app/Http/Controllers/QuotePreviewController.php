<?php

namespace App\Http\Controllers;

use App\Mail\QuoteMailSent;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QuotePreviewController extends Controller
{
    /**
     * Stream the PDF that would be attached to the quote email, for owners and managers of the quote's account.
     */
    public function __invoke(Request $request, Quote $quote): Response
    {
        $user = $request->user();
        $account = $quote->account;

        abort_unless(
            $account !== null && $user->canAccessTenant($account) && ! $user->isMember($account),
            403,
        );

        $mail = new QuoteMailSent($quote);

        if ($mail->usesCustomAttachment()) {
            return Storage::disk(Quote::ATTACHMENT_DISK)->response(
                $quote->attachment_path,
                $mail->attachmentName(),
                ['Content-Type' => 'application/pdf'],
                'inline',
            );
        }

        return $mail->buildPdf()->stream();
    }
}
