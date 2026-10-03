<?php

namespace App\Mcp\Tools\Quotes;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;

/**
 * Shared plumbing for the quote tools: account-scoped lookup, line item
 * schema/rules and the JSON shape returned for a quote.
 */
abstract class QuoteTool extends BaseTool
{
    protected function findQuote(Account $account, mixed $quoteId): Quote
    {
        return Quote::query()->where('account_id', $account->getKey())->findOrFail($quoteId);
    }

    /**
     * @return array<string, Type>
     */
    protected function quoteIdSchema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'quote_id' => $schema->integer()->description('The quote id.')->required(),
        ]);
    }

    protected function itemsSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->items($schema->object([
                'title' => $schema->string()->description('Line item title.')->required(),
                'unit_id' => $schema->integer()->description('Unit id (see list-units).')->required(),
                'quantity' => $schema->number()->description('Quantity, 0 or more.')->required(),
                'unit_price' => $schema->number()->description('Price per unit, 0 or more.')->required(),
                'category_id' => $schema->integer()->description('Optional category id (see list-categories).'),
                'description' => $schema->string()->description('Optional line description.'),
            ]));
    }

    /**
     * Validation rules for the line items; units and categories must belong to the account.
     *
     * @return array<string, mixed>
     */
    protected function itemRules(Account $account): array
    {
        return [
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('account_id', $account->getKey())],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('account_id', $account->getKey())],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function quoteData(Quote $quote): array
    {
        $quote->load('items');

        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'status' => $quote->status->value,
            'client_id' => $quote->client_id,
            'project_id' => $quote->project_id,
            'amount' => $quote->amount,
            'issued_at' => $quote->issued_at?->toIso8601String(),
            'valid_until' => $quote->valid_until?->toIso8601String(),
            'accepted_at' => $quote->accepted_at?->toIso8601String(),
            'notes' => $quote->notes,
            'items' => $quote->items->map(fn (QuoteItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'unit_id' => $item->unit_id,
                'category_id' => $item->category_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ])->all(),
        ];
    }
}
