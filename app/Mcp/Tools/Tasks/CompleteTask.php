<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mark a task as completed.')]
#[IsDestructive(false)]
#[IsIdempotent]
class CompleteTask extends TaskTool
{
    protected ?string $requiredAbility = 'tasks:write';

    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->integer()->required()];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate(['task_id' => ['required', 'integer']]);

        return $this->success(['data' => $this->present($this->tasks->complete($account, $data['task_id']))]);
    }
}
