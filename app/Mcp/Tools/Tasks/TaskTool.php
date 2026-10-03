<?php

namespace App\Mcp\Tools\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use App\Mcp\Tools\BaseTool;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Shared pieces for the task tools: the service, the task payload and its fields.
 */
abstract class TaskTool extends BaseTool
{
    public function __construct(protected TaskService $tasks) {}

    /**
     * @return array<string, Type>
     */
    protected function taskProperties(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Task title (max 255 characters).'),
            'description' => $schema->string()->description('Free-form details.')->nullable(),
            'status' => $schema->string()->enum(array_column(TaskStatus::cases(), 'value'))->description('Defaults to open.'),
            'priority' => $schema->string()->enum(array_column(TaskPriority::cases(), 'value'))->description('Defaults to Medium.'),
            'due_date' => $schema->string()->description('Due date, e.g. 2026-12-31.')->nullable(),
            'client_id' => $schema->integer()->description('Client of this account.')->nullable(),
            'project_id' => $schema->integer()->description('Project of this account.')->nullable(),
            'assigned_user_id' => $schema->integer()->description('User id of an account member.')->nullable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Task $task): array
    {
        return (new TaskResource($task))->resolve();
    }
}
