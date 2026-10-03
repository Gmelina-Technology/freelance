<?php

use App\Enums\AccountRole;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

class ProbeTool extends BaseTool
{
    protected function execute(Request $request, Account $account, User $user): Response
    {
        return $this->success(['account_id' => $account->id]);
    }
}

class AbilityProbeTool extends ProbeTool
{
    protected ?string $requiredAbility = 'quotes:write';
}

class RoleProbeTool extends ProbeTool
{
    protected array $requiredRoles = [AccountRole::Owner, AccountRole::Manager];
}

class ProbeServer extends BillingServer
{
    protected function discoverTools(): array
    {
        return [ProbeTool::class, AbilityProbeTool::class, RoleProbeTool::class];
    }
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('rejects unauthenticated requests', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertStatus(401);
});

it('initializes and lists tools for an authenticated token', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Billing');

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk()
        ->assertJsonStructure(['result' => ['tools']]);
});

it('resolves the first owned account by default', function () {
    Account::factory()->for($this->owner, 'owner')->create();
    Sanctum::actingAs($this->owner, ['*']);

    ProbeServer::tool(ProbeTool::class)
        ->assertOk()
        ->assertSee('"account_id":'.$this->account->id);
});

it('falls back to a membership when the user owns no account', function () {
    $member = User::factory()->create();
    $this->account->users()->attach($member, ['role' => AccountRole::Member->value]);
    Sanctum::actingAs($member, ['*']);

    ProbeServer::tool(ProbeTool::class)
        ->assertSee('"account_id":'.$this->account->id);
});

it('accepts an explicit account the user owns', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    Sanctum::actingAs($this->owner, ['*']);

    ProbeServer::tool(ProbeTool::class, ['account_id' => $second->id])
        ->assertSee('"account_id":'.$second->id);
});

it('rejects an account the user does not belong to', function () {
    $foreign = Account::factory()->for(User::factory()->create(), 'owner')->create();
    Sanctum::actingAs($this->owner, ['*']);

    ProbeServer::tool(ProbeTool::class, ['account_id' => $foreign->id])
        ->assertHasErrors(['do not have access']);
});

it('enforces the required token ability', function () {
    Sanctum::actingAs($this->owner, ['tasks:write']);

    ProbeServer::tool(AbilityProbeTool::class)->assertHasErrors(['quotes:write']);

    Sanctum::actingAs($this->owner, ['quotes:write']);
    ProbeServer::tool(AbilityProbeTool::class)->assertHasNoErrors();
});

it('lets wildcard tokens pass any ability check', function () {
    Sanctum::actingAs($this->owner, ['*']);

    ProbeServer::tool(AbilityProbeTool::class)->assertHasNoErrors();
});

it('limits role-restricted tools to owners and managers', function () {
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $this->account->users()->attach($manager, ['role' => AccountRole::Manager->value]);
    $this->account->users()->attach($member, ['role' => AccountRole::Member->value]);

    Sanctum::actingAs($this->owner, ['*']);
    ProbeServer::tool(RoleProbeTool::class)->assertHasNoErrors();

    Sanctum::actingAs($manager, ['*']);
    ProbeServer::tool(RoleProbeTool::class, ['account_id' => $this->account->id])->assertHasNoErrors();

    Sanctum::actingAs($member, ['*']);
    ProbeServer::tool(RoleProbeTool::class, ['account_id' => $this->account->id])
        ->assertHasErrors(['owner or manager']);
});
