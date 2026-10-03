<?php

namespace App\Mcp\Tools\Lookups;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the categories defined on the account.')]
#[IsReadOnly]
class ListCategories extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $categories = Category::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'color']);

        return $this->success(['data' => $categories->toArray()]);
    }
}
