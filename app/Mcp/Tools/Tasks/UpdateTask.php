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

#[Description('Update a task. Only the fields you pass are changed; pass null to clear description, due_date, client_id, project_id or assigned_user_id.')]
#[IsDestructive(false)]
#[IsIdempotent]
class UpdateTask extends TaskTool
{
    protected ?string $requiredAbility = 'tasks:write';

    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, ['task_id' => $schema->integer()->required()] + $this->taskProperties($schema));
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate(['task_id' => ['required', 'integer']] + $this->tasks->rules($account, updating: true));

        $task = $this->tasks->update($account, $data['task_id'], $data);

        return $this->success(['data' => $this->present($task)]);
    }
}
