<?php

namespace App\Mcp\Tools\Quotes;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one quote with its line items.')]
#[IsReadOnly]
class GetQuote extends QuoteTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->quoteIdSchema($schema);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        return $this->success($this->quoteData($this->findQuote($account, $request->get('quote_id'))));
    }
}
