<?php

namespace App\Mcp\Tools\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List invoices of the account, newest first. Filter by status, client or project.')]
#[IsReadOnly]
class ListInvoices extends InvoiceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(InvoiceStatus::cases(), 'value')),
            'client_id' => $schema->integer(),
            'project_id' => $schema->integer(),
            'limit' => $schema->integer()->min(1)->max(100)->description('Maximum invoices to return (default 50).'),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $invoices = Invoice::query()
            ->where('account_id', $account->getKey())
            ->with('client:id,name', 'project:id,name')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->latest('id')
            ->limit($filters['limit'] ?? 50)
            ->get();

        return $this->success([
            'invoices' => $invoices->map(fn (Invoice $invoice): array => $this->present($invoice))->all(),
        ]);
    }
}
