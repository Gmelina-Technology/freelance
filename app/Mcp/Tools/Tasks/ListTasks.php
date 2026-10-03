<?php

namespace App\Mcp\Tools\Tasks;

use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List tasks of the account, newest first. Filter by status, overdue, project or client.')]
#[IsReadOnly]
class ListTasks extends TaskTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(TaskStatus::cases(), 'value')),
            'overdue' => $schema->boolean()->description('Only tasks past their due date and not completed.'),
            'project_id' => $schema->integer(),
            'client_id' => $schema->integer(),
            'limit' => $schema->integer()->description('Maximum number of tasks (default 25, max 100).'),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'overdue' => ['nullable', 'boolean'],
            'project_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tasks = $this->tasks->list($account, $filters);

        return $this->success(['data' => $tasks->map(fn (Task $task) => $this->present($task))->all()]);
    }
}
