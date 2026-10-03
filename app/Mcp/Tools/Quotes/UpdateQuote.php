<?php

namespace App\Mcp\Tools\Quotes;

use App\Exceptions\QuoteTransitionException;
use App\Models\Account;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Edit a draft, declined or expired quote (declined and expired ones return to draft). When items are given they replace all existing line items.')]
#[IsIdempotent]
class UpdateQuote extends QuoteTool
{
    protected ?string $requiredAbility = 'quotes:write';

    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'quote_id' => $schema->integer()->description('The quote id.')->required(),
            'client_id' => $schema->integer()->description('Move the quote to another client (project_id is then required).'),
            'project_id' => $schema->integer()->description('Move the quote to another project of the client.'),
            'items' => $this->itemsSchema($schema)->description('Replaces all line items when given.'),
            'notes' => $schema->string()->description('Notes shown on the quote.'),
            'issued_at' => $schema->string()->description('Issue date (ISO 8601).'),
            'valid_until' => $schema->string()->description('Expiry date (ISO 8601).'),
        ]);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $accountId = $account->getKey();
        $quote = $this->findQuote($account, $request->get('quote_id'));
        $clientId = $request->get('client_id') ?? $quote->client_id;

        $data = $request->validate([
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->where('account_id', $accountId)],
            'project_id' => [
                'sometimes',
                'integer',
                Rule::exists('projects', 'id')->where('account_id', $accountId)->where('client_id', $clientId),
            ],
            'items' => ['sometimes', 'array', 'min:1'],
            ...$this->itemRules($account),
            'notes' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ]);

        if (isset($data['client_id']) && ! isset($data['project_id']) && (int) $data['client_id'] !== $quote->client_id) {
            return $this->failure('Validation failed: project_id is required when moving a quote to another client.');
        }

        $attributes = array_intersect_key($data, array_flip(['client_id', 'project_id', 'notes', 'issued_at', 'valid_until']));

        try {
            $quote = app(QuoteService::class)->update($quote, $attributes, $data['items'] ?? null);
        } catch (QuoteTransitionException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($this->quoteData($quote));
    }
}
