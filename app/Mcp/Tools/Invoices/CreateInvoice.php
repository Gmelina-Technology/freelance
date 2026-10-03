<?php

namespace App\Mcp\Tools\Invoices;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a draft invoice by hand. Each item bills one billable task of the project at the given unit, quantity and unit price; the total is quantity x unit price summed over the items.')]
class CreateInvoice extends InvoiceTool
{
    protected ?string $requiredAbility = 'invoices:write';

    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];

    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'client_id' => $schema->integer()->required(),
            'project_id' => $schema->integer()->required()->description('Must belong to the client.'),
            'items' => $schema->array()->required()->min(1)->items($schema->object([
                'task_id' => $schema->integer()->required()->description('A billable task of the project, not yet invoiced.'),
                'unit_id' => $schema->integer()->required(),
                'quantity' => $schema->number()->required()->min(0),
                'unit_price' => $schema->number()->required()->min(0),
            ])),
            'notes' => $schema->string(),
            'issued_at' => $schema->string()->description('Date; defaults to now.'),
            'due_date' => $schema->string()->description('Date; defaults to 7 weekdays from now.'),
        ]);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'project_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.task_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $invoice = app(InvoiceService::class)->createManual(
            $account,
            collect($data)->except('items')->all(),
            array_values($data['items']),
        );

        return $this->success($this->present($invoice, withItems: true));
    }
}
