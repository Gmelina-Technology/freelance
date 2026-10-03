<?php

namespace App\Mcp\Tools\Quotes;

use App\Enums\AccountRole;
use App\Exceptions\QuoteNotAcceptableException;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Accept a quote and create one billable task per line item on the project board. Accepting an already accepted quote changes nothing.')]
#[IsDestructive(false)]
#[IsIdempotent]
class AcceptQuote extends QuoteTool
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
            $quote = app(QuoteService::class)->accept($quote);
        } catch (QuoteNotAcceptableException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success([
            ...$this->quoteData($quote),
            'tasks' => $quote->tasks()->get()->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
            ])->all(),
        ]);
    }
}
