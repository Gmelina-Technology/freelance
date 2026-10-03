<?php

namespace App\Mcp\Tools\Quotes;

use App\Models\Account;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a draft quote with line items for a client project. The total is computed from quantity x unit_price.')]
class CreateQuote extends QuoteTool
{
    protected ?string $requiredAbility = 'quotes:write';

    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'client_id' => $schema->integer()->description('Client id (see list-clients).')->required(),
            'project_id' => $schema->integer()->description('Project id; it must belong to the client.')->required(),
            'items' => $this->itemsSchema($schema)->description('At least one line item.')->required(),
            'notes' => $schema->string()->description('Optional notes shown on the quote.'),
            'issued_at' => $schema->string()->description('Issue date (ISO 8601). Defaults to now.'),
            'valid_until' => $schema->string()->description('Expiry date (ISO 8601). Defaults to 30 days from now.'),
        ]);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $accountId = $account->getKey();

        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('account_id', $accountId)],
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')
                    ->where('account_id', $accountId)
                    ->where('client_id', $request->get('client_id')),
            ],
            'items' => ['required', 'array', 'min:1'],
            ...$this->itemRules($account),
            'notes' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
        ]);

        $quote = app(QuoteService::class)->create([
            'account_id' => $accountId,
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'],
            'notes' => $data['notes'] ?? null,
            'issued_at' => $data['issued_at'] ?? now(),
            'valid_until' => $data['valid_until'] ?? now()->addDays(30),
        ], $data['items']);

        return $this->success($this->quoteData($quote));
    }
}
