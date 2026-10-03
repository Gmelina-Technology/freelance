<?php

namespace App\Mcp\Tools\Quotes;

use App\Enums\AccountRole;
use App\Exceptions\QuoteTransitionException;
use App\Models\Account;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Mark a sent quote as declined. A declined quote can be revised with update-quote.')]
#[IsDestructive(false)]
class DeclineQuote extends QuoteTool
{
    protected ?string $requiredAbility = 'quotes:write';

    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];

    public function schema(JsonSchema $schema): array
    {
        return $this->quoteIdSchema($schema);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $quote = $this->findQuote($account, $request->get('quote_id'));

        try {
            $quote = app(QuoteService::class)->decline($quote);
        } catch (QuoteTransitionException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($this->quoteData($quote));
    }
}
