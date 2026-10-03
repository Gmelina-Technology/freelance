<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Account;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    /**
     * List tasks for the authenticated account.
     *
     * Query params: status (enum), overdue (bool), limit (int, default 25).
     */
    public function index(Request $request): JsonResponse
    {
        $tasks = $this->tasks->list($this->account($request), [
            'status' => $request->filled('status') ? $request->string('status')->toString() : null,
            'overdue' => $request->boolean('overdue'),
            'limit' => $request->integer('limit', 25),
        ]);

        return TaskResource::collection($tasks)->response();
    }

    /**
     * Create a task scoped to the authenticated account.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($this->account($request), $request->validated());

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    /**
     * Update a task that belongs to the authenticated account.
     */
    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        $model = $this->tasks->update($this->account($request), $task, $request->validated());

        return (new TaskResource($model))->response();
    }

    private function account(Request $request): Account
    {
        return $request->attributes->get('account');
    }
}
