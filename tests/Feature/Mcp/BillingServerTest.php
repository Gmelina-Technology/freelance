<?php

use App\Enums\AccountCapability;
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

class CapabilityProbeTool extends ProbeTool
{
    protected ?AccountCapability $requiredCapability = AccountCapability::ManageInvoices;
}

class OwnerOnlyProbeTool extends ProbeTool
{
    protected ?AccountCapability $requiredCapability = AccountCapability::ManageClients;
}

class ProbeServer extends BillingServer
{
    protected function discoverTools(): array
    {
        return [ProbeTool::class, AbilityProbeTool::class, CapabilityProbeTool::class, OwnerOnlyProbeTool::class];
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

it('works for a manager bound to the account', function () {
    $manager = makeAccountUser($this->account, AccountRole::Manager);
    actingAsToken($manager, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class)
        ->assertSee('"account_id":'.$this->account->id);
});

it('refuses a member token before the tool runs', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class)
        ->assertHasErrors(['API access is not available for your role on this account.'])
        ->assertDontSee('account_id');
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
    $manager = makeAccountUser($this->account, AccountRole::Manager);
    actingAsToken($manager, ['*'], $this->account);

    ProbeServer::tool(ProbeTool::class)->assertHasNoErrors();

    $this->account->users()->detach($manager);

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

it('limits capability-restricted tools to the roles that hold the capability', function () {
    $manager = makeAccountUser($this->account, AccountRole::Manager);
    $member = makeAccountUser($this->account, AccountRole::Member);

    actingAsToken($this->owner, ['*']);
    ProbeServer::tool(CapabilityProbeTool::class)->assertHasNoErrors();

    actingAsToken($manager, ['*']);
    ProbeServer::tool(CapabilityProbeTool::class)->assertHasNoErrors();

    actingAsToken($member, ['*']);
    ProbeServer::tool(CapabilityProbeTool::class)
        ->assertHasErrors(['API access is not available']);
});

it('checks the tool capability on its own once the role may use the API', function () {
    $manager = makeAccountUser($this->account, AccountRole::Manager);

    actingAsToken($this->owner, ['*']);
    ProbeServer::tool(OwnerOnlyProbeTool::class)->assertHasNoErrors();

    actingAsToken($manager, ['*']);
    ProbeServer::tool(OwnerOnlyProbeTool::class)
        ->assertHasErrors(['requires the owner role'])
        ->assertDontSee('account_id');
});
