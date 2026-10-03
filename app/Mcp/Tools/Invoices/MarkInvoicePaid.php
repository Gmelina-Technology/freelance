<?php

namespace App\Mcp\Tools\Invoices;

use App\Enums\AccountRole;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Models\Account;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Mark a sent invoice as paid: its tasks become paid and a sale is recorded.')]
class MarkInvoicePaid extends InvoiceTool
{
    protected ?string $requiredAbility = 'invoices:write';

    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];

    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'invoice_id' => $schema->integer()->required(),
        ]);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $request->validate(['invoice_id' => ['required', 'integer']]);

        $invoice = $this->findInvoice($account, $request->get('invoice_id'));

        try {
            app(InvoiceService::class)->markAsPaid($invoice, onlyFromSent: true);
        } catch (InvalidInvoiceTransitionException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($this->present($invoice->refresh()));
    }
}
