<?php

namespace App\Mcp\Tools\Invoices;

use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the completed, billable tasks of a project that generate-invoice would bill (tasks of accepted quotes, with the agreed quantity and price).')]
#[IsReadOnly]
class ListBillableTasks extends InvoiceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $request->validate(['project_id' => ['required', 'integer']]);

        $project = $account->projects()->findOrFail($request->get('project_id'));

        $tasks = app(InvoiceService::class)->billableTasksFor($project);

        return $this->success([
            'project_id' => $project->id,
            'tasks' => $tasks->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'quantity' => (float) $task->quoteItem->quantity,
                'unit_price' => (float) $task->quoteItem->unit_price,
                'unit_id' => $task->quoteItem->unit_id,
                'quote_id' => $task->quoteItem->quote_id,
            ])->all(),
        ]);
    }
}
