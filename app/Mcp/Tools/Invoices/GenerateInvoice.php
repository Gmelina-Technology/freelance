<?php

namespace App\Mcp\Tools\Invoices;

use App\Enums\AccountRole;
use App\Exceptions\NoBillableTasksException;
use App\Models\Account;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a draft invoice from the completed, billable tasks of a project, billed at the prices of their accepted quote lines. Pass task_ids to bill only some of them.')]
class GenerateInvoice extends InvoiceTool
{
    protected ?string $requiredAbility = 'invoices:write';

    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->required(),
            'task_ids' => $schema->array()->items($schema->integer())
                ->description('Optional subset of the billable task ids; defaults to all of them.'),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'task_ids' => ['nullable', 'array', 'min:1'],
            'task_ids.*' => ['integer'],
        ]);

        $project = $account->projects()->findOrFail($data['project_id']);

        try {
            $invoice = app(InvoiceService::class)->generateFromCompletedTasks($project, $data['task_ids'] ?? null);
        } catch (NoBillableTasksException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($this->present($invoice, withItems: true));
    }
}
