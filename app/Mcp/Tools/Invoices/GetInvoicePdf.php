<?php

namespace App\Mcp\Tools\Invoices;

use App\Mail\InvoiceMailSent;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Render the invoice as a PDF and return it base64-encoded.')]
#[IsReadOnly]
class GetInvoicePdf extends InvoiceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $request->validate(['invoice_id' => ['required', 'integer']]);

        $invoice = $this->findInvoice($account, $request->get('invoice_id'));

        $pdf = (new InvoiceMailSent($invoice))->buildPdf();

        return $this->success([
            'filename' => "Invoice-{$invoice->number}.pdf",
            'mime_type' => 'application/pdf',
            'base64' => base64_encode($pdf),
        ]);
    }
}
