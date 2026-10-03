<?php

namespace App\Mcp\Tools\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List quotes, newest first. Optionally filter by status, client_id or project_id.')]
#[IsReadOnly]
class ListQuotes extends QuoteTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(QuoteStatus::cases(), 'value'))
                ->description('Only quotes with this status.'),
            'client_id' => $schema->integer()->description('Only quotes for this client.'),
            'project_id' => $schema->integer()->description('Only quotes for this project.'),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $quotes = Quote::query()
            ->where('account_id', $account->getKey())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->latest('id')
            ->get();

        return $this->success([
            'quotes' => $quotes->map(fn (Quote $quote): array => [
                'id' => $quote->id,
                'number' => $quote->number,
                'status' => $quote->status->value,
                'client_id' => $quote->client_id,
                'project_id' => $quote->project_id,
                'amount' => $quote->amount,
                'issued_at' => $quote->issued_at?->toIso8601String(),
                'valid_until' => $quote->valid_until?->toIso8601String(),
            ])->all(),
        ]);
    }
}
