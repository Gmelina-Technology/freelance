<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function rateLimitMcpInitialize(): array
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

it('allows requests up to the REST API limit for a token', function () {
    config(['api.rate_limits.api' => 2]);
    actingAsToken($this->owner);

    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks')->assertOk();
});

it('refuses the request over the REST API limit with Retry-After and a JSON body', function () {
    config(['api.rate_limits.api' => 2]);
    actingAsToken($this->owner);

    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks')->assertOk();

    $this->getJson('/api/tasks')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['message']);
});

it('answers 429 as JSON even when the client sends no Accept header', function () {
    config(['api.rate_limits.api' => 1]);
    actingAsToken($this->owner);

    $this->get('/api/tasks')->assertOk();

    $response = $this->get('/api/tasks');

    $response->assertStatus(429)->assertHeader('Retry-After');
    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($response->json('message'))->toBeString();
});

it('counts each token of the same user separately', function () {
    config(['api.rate_limits.api' => 1]);

    actingAsToken($this->owner);
    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks')->assertStatus(429);

    actingAsToken($this->owner);
    $this->getJson('/api/tasks')->assertOk();
});

it('counts the MCP endpoint separately from the REST API', function () {
    config(['api.rate_limits.api' => 1, 'api.rate_limits.mcp' => 2]);
    actingAsToken($this->owner);

    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks')->assertStatus(429);

    $this->postJson('/mcp', rateLimitMcpInitialize())->assertOk();
    $this->postJson('/mcp', rateLimitMcpInitialize())->assertOk();
    $this->postJson('/mcp', rateLimitMcpInitialize())
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('answers an MCP request over the limit as JSON without an Accept header', function () {
    config(['api.rate_limits.mcp' => 1]);
    actingAsToken($this->owner);

    $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(rateLimitMcpInitialize()))->assertOk();

    $response = $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(rateLimitMcpInitialize()));

    $response->assertStatus(429);
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('still counts a member token that bind.account refuses', function () {
    config(['api.rate_limits.api' => 1]);
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    $this->getJson('/api/tasks')->assertForbidden();
    $this->getJson('/api/tasks')->assertStatus(429);
});

it('treats a limit below one as one request per minute', function () {
    config(['api.rate_limits.api' => 0]);
    actingAsToken($this->owner);

    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks')->assertStatus(429);
});

it('defaults both limits to 60 requests per minute', function () {
    expect(config('api.rate_limits.api'))->toBe(60)
        ->and(config('api.rate_limits.mcp'))->toBe(60);
});

it('keeps the Livewire upload throttle', function () {
    expect(FileUploadConfiguration::middleware())->toBe('throttle:60,1');
});
