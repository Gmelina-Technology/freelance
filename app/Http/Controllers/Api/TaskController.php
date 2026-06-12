<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Models\Account;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * List tasks for the authenticated account.
     *
     * Query params: status (enum), overdue (bool), limit (int, default 25).
     */
    public function index(Request $request): JsonResponse
    {
        $account = $this->account($request);

        $query = $account->tasks()->with('client')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('overdue')) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->where('status', '!=', 'completed');
        }

        $tasks = $query->limit((int) $request->integer('limit', 25))->get();

        return response()->json([
            'data' => $tasks->map(fn (Task $task) => $this->present($task))->all(),
        ]);
    }

    /**
     * Create a task scoped to the authenticated account.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $account = $this->account($request);

        $task = $account->tasks()->create([
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'open'),
            'priority' => $request->input('priority', 'Medium'),
            'due_date' => $request->input('due_date'),
            'client_id' => $request->input('client_id'),
            'project_id' => $request->input('project_id'),
        ]);

        return response()->json(['data' => $this->present($task)], 201);
    }

    /**
     * Update a task that belongs to the authenticated account.
     */
    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        $account = $this->account($request);

        $model = $account->tasks()->findOrFail($task);

        $model->fill($request->only([
            'title', 'description', 'status', 'priority', 'due_date', 'client_id', 'project_id',
        ]));
        $model->save();

        return response()->json(['data' => $this->present($model->fresh('client'))]);
    }

    private function account(Request $request): Account
    {
        return $request->attributes->get('account');
    }

    private function present(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status?->value,
            'priority' => $task->priority?->value,
            'due_date' => $task->due_date?->toDateString(),
            'client' => $task->client?->name,
            'project_id' => $task->project_id,
        ];
    }
}
