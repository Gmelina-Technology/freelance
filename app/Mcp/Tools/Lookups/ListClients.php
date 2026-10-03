<?php

namespace App\Mcp\Tools\Lookups;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the clients of the account (id, name, email, currency) to use as client_id elsewhere.')]
#[IsReadOnly]
class ListClients extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $clients = $account->clients()->orderBy('name')->get(['id', 'name', 'email', 'currency_code']);

        return $this->success(['data' => $clients->toArray()]);
    }
}
