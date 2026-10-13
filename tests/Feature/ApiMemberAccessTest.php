<?php

use App\Enums\AccountRole;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Tasks\ListTasks;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const API_ROLE_MESSAGE = 'API access is not available for your role on this account.';

/**
 * @return array<string, mixed>
 */
function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ];
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('lets owners and managers use the REST API and MCP', function () {
    $manager = makeAccountUser($this->account, AccountRole::Manager);

    foreach ([$this->owner, $manager] as $user) {
        actingAsToken($user, ['*'], $this->account);

        $this->getJson('/api/tasks')->assertOk();
        $this->postJson('/mcp', mcpInitializePayload())->assertOk();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->assertOk();
    }
});

it('treats the account owner_id as owner even without a pivot row', function () {
    expect($this->account->users()->where('user_id', $this->owner->id)->exists())->toBeFalse();

    actingAsToken($this->owner, ['*'], $this->account);

    $this->getJson('/api/tasks')->assertOk();
    $this->postJson('/mcp', mcpInitializePayload())->assertOk();
});

it('refuses a member token on the REST API', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    $this->getJson('/api/tasks')
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);

    $this->postJson('/api/tasks', ['title' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);

    expect($this->account->tasks()->count())->toBe(0);
});

it('gives a member token without the write ability the role message, not the ability message', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    $task = Task::factory()->for($this->account)->create();
    actingAsToken($member, [], $this->account);

    $this->postJson('/api/tasks', ['title' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Changed'])
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);

    expect($this->account->tasks()->count())->toBe(1)
        ->and($task->fresh()->title)->toBe($task->title);
});

it('lets a pivot owner use the REST API and MCP', function () {
    $pivotOwner = makeAccountUser($this->account, AccountRole::Owner);
    actingAsToken($pivotOwner, ['*'], $this->account);

    $this->getJson('/api/tasks')->assertOk();
    $this->postJson('/mcp', mcpInitializePayload())->assertOk();
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->assertOk();
});

it('refuses a member token on every MCP method', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    $this->postJson('/mcp', mcpInitializePayload())
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertForbidden()
        ->assertJsonPath('message', API_ROLE_MESSAGE);
});

it('refuses a member token on a direct tool call without naming any resource', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    BillingServer::tool(ListTasks::class)
        ->assertHasErrors([API_ROLE_MESSAGE])
        ->assertDontSee($this->account->name);
});

it('follows a role change on the very next request without touching the token', function () {
    $user = makeAccountUser($this->account, AccountRole::Manager);
    actingAsToken($user, ['*'], $this->account);

    $this->getJson('/api/tasks')->assertOk();

    $this->account->users()->updateExistingPivot($user->id, ['role' => AccountRole::Member->value]);
    $user->unsetRelation('accounts');

    $this->getJson('/api/tasks')->assertForbidden()->assertJsonPath('message', API_ROLE_MESSAGE);
    expect($user->tokens()->count())->toBe(1);

    $this->account->users()->updateExistingPivot($user->id, ['role' => AccountRole::Manager->value]);
    $user->unsetRelation('accounts');

    $this->getJson('/api/tasks')->assertOk();
});
