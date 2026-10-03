<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete a task of the account.')]
#[IsDestructive]
class DeleteTask extends TaskTool
{
    protected ?string $requiredAbility = 'tasks:write';

    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->integer()->required()];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate(['task_id' => ['required', 'integer']]);

        $this->tasks->delete($account, $data['task_id']);

        return $this->success(['deleted' => true, 'task_id' => $data['task_id']]);
    }
}
