<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class TaskService
{
    /**
     * Fields a caller may set on a task.
     *
     * @var array<int, string>
     */
    public const WRITABLE_FIELDS = [
        'title', 'description', 'status', 'priority', 'due_date', 'client_id', 'project_id', 'assigned_user_id',
    ];

    /**
     * Validation rules for creating or updating a task, with every client, project
     * and assignee constrained to the account.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(Account $account, bool $updating = false): array
    {
        return [
            'title' => $updating ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => $updating ? ['sometimes', Rule::enum(TaskStatus::class)] : ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => $updating ? ['sometimes', Rule::enum(TaskPriority::class)] : ['nullable', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('account_id', $account->id)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('account_id', $account->id)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('account_user', 'user_id')->where('account_id', $account->id)],
        ];
    }

    /**
     * List the account's tasks, newest first.
     *
     * @param  array{status?: ?string, overdue?: bool, project_id?: ?int, client_id?: ?int, limit?: ?int}  $filters
     * @return Collection<int, Task>
     */
    public function list(Account $account, array $filters = []): Collection
    {
        $query = $account->tasks()->with('client')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['overdue'])) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->where('status', '!=', TaskStatus::COMPLETED);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        return $query->limit($filters['limit'] ?? 25)->get();
    }

    public function find(Account $account, int $taskId): Task
    {
        return $account->tasks()->with('client')->findOrFail($taskId);
    }

    /**
     * @param  array<string, mixed>  $data  validated task attributes
     */
    public function create(Account $account, array $data): Task
    {
        $task = $account->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? TaskStatus::OPEN->value,
            'priority' => $data['priority'] ?? TaskPriority::Medium->value,
            'due_date' => $data['due_date'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
        ]);

        return $task->load('client');
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes to change
     */
    public function update(Account $account, int $taskId, array $data): Task
    {
        $task = $account->tasks()->findOrFail($taskId);

        $task->fill(array_intersect_key($data, array_flip(self::WRITABLE_FIELDS)));
        $task->save();

        return $task->fresh('client');
    }

    public function complete(Account $account, int $taskId): Task
    {
        return $this->update($account, $taskId, ['status' => TaskStatus::COMPLETED->value]);
    }

    public function delete(Account $account, int $taskId): void
    {
        $account->tasks()->findOrFail($taskId)->delete();
    }
}
