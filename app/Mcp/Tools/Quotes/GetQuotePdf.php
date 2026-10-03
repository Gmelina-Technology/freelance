<?php

namespace App\Mcp\Tools\Quotes;

use App\Mail\QuoteMailSent;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Render the quote PDF and return it base64-encoded.')]
#[IsReadOnly]
class GetQuotePdf extends QuoteTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->quoteIdSchema($schema);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $mail = new QuoteMailSent($this->findQuote($account, $request->get('quote_id')));

        return $this->success([
            'filename' => $mail->attachmentName(),
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode($mail->pdfContent()),
        ]);
    }
}
