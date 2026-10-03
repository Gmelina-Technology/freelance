<?php

namespace App\Mcp\Tools\Lookups;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the projects of the account, optionally only those of one client.')]
#[IsReadOnly]
class ListProjects extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->withAccountSchema($schema, [
            'client_id' => $schema->integer()->description('Only projects of this client.'),
        ]);
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate(['client_id' => ['nullable', 'integer']]);

        $projects = $account->projects()
            ->when($data['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'client_id' => $project->client_id,
                'status' => $project->status?->value,
                'due_date' => $project->due_date?->toDateString(),
            ]);

        return $this->success(['data' => $projects->all()]);
    }
}
