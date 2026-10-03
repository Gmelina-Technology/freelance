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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Void a draft, sent or overdue invoice. Its tasks become billable again.')]
#[IsDestructive]
class VoidInvoice extends InvoiceTool
{
    protected ?string $requiredAbility = 'invoices:write';

    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];

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

        try {
            app(InvoiceService::class)->void($invoice);
        } catch (InvalidInvoiceTransitionException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($this->present($invoice->refresh()));
    }
}
