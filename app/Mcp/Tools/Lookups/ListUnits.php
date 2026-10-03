<?php

namespace App\Mcp\Tools\Lookups;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the units (e.g. hours, days) defined on the account.')]
#[IsReadOnly]
class ListUnits extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $units = Unit::where('account_id', $account->id)->orderBy('name')->get(['id', 'name']);

        return $this->success(['data' => $units->toArray()]);
    }
}
