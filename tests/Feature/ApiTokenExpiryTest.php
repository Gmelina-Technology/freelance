<?php

use App\Filament\App\Pages\ApiTokens;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Call the API with a real bearer token, so the guard cannot reuse an earlier request's user.
 */
function bearerGet(string $uri, string $plainTextToken): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($plainTextToken)->getJson($uri);
}

function bearerMcp(string $plainTextToken): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($plainTextToken)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->for($this->user, 'owner')->create();
});

it('creates a token that expires after the chosen number of days', function (string $choice, int $days) {
    bindFilamentTenant($this->user, $this->account);

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Timed', 'abilities' => [], 'expires_in' => $choice])
        ->assertHasNoActionErrors();

    $expiresAt = $this->user->tokens()->sole()->expires_at;

    expect($expiresAt->between(now()->addDays($days)->subMinute(), now()->addDays($days)->addMinute()))->toBeTrue();
})->with([
    '30 days' => ['30', 30],
    '90 days' => ['90', 90],
    '1 year' => ['365', 365],
]);

it('creates a token without expiry when no expiry is chosen', function () {
    bindFilamentTenant($this->user, $this->account);

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Forever', 'abilities' => [], 'expires_in' => 'never'])
        ->assertHasNoActionErrors();

    expect($this->user->tokens()->sole()->expires_at)->toBeNull();
});

it('uses the configured default when the expiry is left alone', function (mixed $configured, string $expected, ?int $days) {
    config(['api.tokens.default_expiry' => $configured]);
    bindFilamentTenant($this->user, $this->account);

    expect(ApiTokens::defaultExpiryOption())->toBe($expected);

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Default', 'abilities' => []])
        ->assertHasNoActionErrors();

    $expiresAt = $this->user->tokens()->sole()->expires_at;

    $days === null
        ? expect($expiresAt)->toBeNull()
        : expect($expiresAt->between(now()->addDays($days)->subMinute(), now()->addDays($days)->addMinute()))->toBeTrue();
})->with([
    'unset falls back to 90' => [90, '90', 90],
    'a listed value' => ['30', '30', 30],
    'never' => ['never', 'never', null],
    'empty string' => ['', 'never', null],
    'null' => [null, 'never', null],
    'an unlisted value' => [7, '90', 90],
]);

it('refuses an expiry that is not offered and creates no token', function () {
    bindFilamentTenant($this->user, $this->account);

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Bad', 'abilities' => [], 'expires_in' => '7'])
        ->assertHasActionErrors(['expires_in']);

    expect($this->user->tokens()->count())->toBe(0);
});

it('shows when each token expires', function () {
    bindFilamentTenant($this->user, $this->account);
    $timed = $this->user->createAccountToken($this->account, 'Timed', ['*'], now()->addDays(30))->accessToken;
    $open = $this->user->createAccountToken($this->account, 'Open')->accessToken;

    Livewire::test(ApiTokens::class)
        ->assertCanSeeTableRecords([$timed, $open])
        ->assertTableColumnExists('expires_at')
        ->assertTableColumnStateSet('expires_at', null, $open);
});

it('accepts tokens that have not expired, including tokens issued without an expiry', function () {
    $timed = $this->user->createAccountToken($this->account, 'Timed', ['*'], now()->addDays(30))->plainTextToken;
    $legacy = $this->user->createAccountToken($this->account, 'Legacy')->plainTextToken;

    bearerGet('/api/tasks', $timed)->assertOk();
    bearerGet('/api/tasks', $legacy)->assertOk();
    bearerMcp($timed)->assertOk();
    bearerMcp($legacy)->assertOk();
});

it('refuses a token past its expiry on the REST API and on MCP', function () {
    $token = $this->user->createAccountToken($this->account, 'Short', ['*'], now()->addDays(30))->plainTextToken;

    bearerGet('/api/tasks', $token)->assertOk();

    $this->travel(31)->days();

    bearerGet('/api/tasks', $token)->assertUnauthorized();
    bearerMcp($token)->assertUnauthorized();
});

it('prunes only tokens that expired more than 24 hours ago', function () {
    $old = $this->user->createAccountToken($this->account, 'Old', ['*'], now()->subHours(25))->accessToken;
    $recent = $this->user->createAccountToken($this->account, 'Recent', ['*'], now()->subHour())->accessToken;
    $future = $this->user->createAccountToken($this->account, 'Future', ['*'], now()->addDay())->accessToken;
    $never = $this->user->createAccountToken($this->account, 'Never')->accessToken;

    $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

    expect($this->user->tokens()->pluck('id')->all())
        ->not->toContain($old->id)
        ->toContain($recent->id, $future->id, $never->id);
});

it('creates a CLI token with the chosen expiry', function () {
    $this->artisan('freelance:api-token', [
        'email' => $this->user->email,
        'account' => $this->account->id,
        '--expires-in-days' => '30',
    ])->assertSuccessful();

    expect($this->user->tokens()->sole()->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()))->toBeTrue();
});

it('creates a CLI token without expiry for never', function () {
    $this->artisan('freelance:api-token', [
        'email' => $this->user->email,
        'account' => $this->account->id,
        '--expires-in-days' => 'never',
    ])->assertSuccessful();

    expect($this->user->tokens()->sole()->expires_at)->toBeNull();
});

it('refuses a CLI expiry that is not offered and creates no token', function () {
    $this->artisan('freelance:api-token', [
        'email' => $this->user->email,
        'account' => $this->account->id,
        '--expires-in-days' => '12',
    ])->assertFailed();

    expect($this->user->tokens()->count())->toBe(0);
});
