<?php

use App\Enums\AccountRole;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

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
    actingAsToken($this->owner, ['*']);

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

it('resolves the account the token is bound to', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    actingAsToken($this->owner, ['*'], $second);

    ProbeServer::tool(ProbeTool::class)
        ->assertOk()
        ->assertSee('"account_id":'.$second->id);
});

it('works for a member bound to the account', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class)
        ->assertSee('"account_id":'.$this->account->id);
});

it('ignores an account_id argument and keeps acting on the bound account', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    actingAsToken($this->owner, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class, ['account_id' => $second->id])
        ->assertSee('"account_id":'.$this->account->id);
});

it('denies legacy tokens that are not bound to an account', function () {
    actingAsToken($this->owner, ['*'], bound: false);

    ProbeServer::tool(ProbeTool::class)->assertHasErrors(['not bound to an account']);
});

it('stops working once the user is removed from the account', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class)->assertHasNoErrors();

    $this->account->users()->detach($member);

    ProbeServer::tool(ProbeTool::class)->assertHasErrors(['do not have access']);
});

it('advertises no account_id property on any tool', function () {
    actingAsToken($this->owner);

    $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk();

    $tools = $response->json('result.tools');

    expect($tools)->not->toBeEmpty();

    foreach ($tools as $tool) {
        expect($tool['inputSchema']['properties'] ?? [])->not->toHaveKey('account_id');
    }
});

it('enforces the required token ability', function () {
    actingAsToken($this->owner, ['tasks:write']);

    ProbeServer::tool(AbilityProbeTool::class)->assertHasErrors(['quotes:write']);

    actingAsToken($this->owner, ['quotes:write']);
    ProbeServer::tool(AbilityProbeTool::class)->assertHasNoErrors();
});

it('lets wildcard tokens pass any ability check', function () {
    actingAsToken($this->owner, ['*']);

    ProbeServer::tool(AbilityProbeTool::class)->assertHasNoErrors();
});

it('limits role-restricted tools to owners and managers', function () {
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $this->account->users()->attach($manager, ['role' => AccountRole::Manager->value]);
    $this->account->users()->attach($member, ['role' => AccountRole::Member->value]);

    actingAsToken($this->owner, ['*']);
    ProbeServer::tool(RoleProbeTool::class)->assertHasNoErrors();

    actingAsToken($manager, ['*']);
    ProbeServer::tool(RoleProbeTool::class)->assertHasNoErrors();

    actingAsToken($member, ['*']);
    ProbeServer::tool(RoleProbeTool::class)
        ->assertHasErrors(['owner or manager']);
});
