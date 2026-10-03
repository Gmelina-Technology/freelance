<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Create a task in the account. Client, project and assignee must belong to the account.')]
#[IsDestructive(false)]
class CreateTask extends TaskTool
{
    protected ?string $requiredAbility = 'tasks:write';

    public function schema(JsonSchema $schema): array
    {
        $properties = $this->taskProperties($schema);
        $properties['title'] = $properties['title']->required();

        return $properties;
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate($this->tasks->rules($account));

        return $this->success(['data' => $this->present($this->tasks->create($account, $data))]);
    }
}
