<?php

namespace App\Mcp\Tools\Invoices;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one invoice with its line items.')]
#[IsReadOnly]
class GetInvoice extends InvoiceTool
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

        return $this->success($this->present($invoice, withItems: true));
    }
}
