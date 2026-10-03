<?php

namespace App\Mcp\Tools\Lookups;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the people involved in a project: its assigned team members and the client with its points of contact.')]
#[IsReadOnly]
class GetProjectPeople extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('The project to look up.')->required(),
        ];
    }

    protected function execute(Request $request, Account $account, User $user): Response
    {
        $data = $request->validate(['project_id' => ['required', 'integer']]);

        $project = $account->projects()->with(['client', 'assignees'])->findOrFail($data['project_id']);
        $client = $project->client;

        return $this->success([
            'project_id' => $project->id,
            'project_name' => $project->name,
            'assignees' => $project->assignees
                ->map(fn (User $assignee) => [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'email' => $assignee->email,
                ])
                ->values()
                ->all(),
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'points_of_contact' => $client->pocs ?? [],
            ] : null,
        ]);
    }
}
