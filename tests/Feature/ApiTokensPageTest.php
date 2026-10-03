<?php

use App\Filament\App\Pages\ApiTokens;
use App\Models\Account;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->for($this->user, 'owner')->create();

    bindFilamentTenant($this->user, $this->account);
});

it('lists only the current user tokens', function () {
    $mine = $this->user->createToken('My laptop', ['tasks:write'])->accessToken;
    $theirs = User::factory()->create()->createToken('Their secret', ['*'])->accessToken;

    Livewire::test(ApiTokens::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertSee('My laptop')
        ->assertDontSee('Their secret');
});

it('creates a token with the chosen abilities and reveals it once', function () {
    $component = Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Claude Code', 'abilities' => ['tasks:write', 'invoices:write']])
        ->assertActionMounted('revealToken');

    $token = $this->user->tokens()->sole();
    $revealed = $component->instance()->mountedActions[0]['arguments']['token'];

    expect($token->name)->toBe('Claude Code')
        ->and($token->abilities)->toBe(['tasks:write', 'invoices:write'])
        ->and($revealed)->toStartWith($token->id.'|')
        ->and($token->token)->not->toContain($revealed);

    $component->unmountAction()->assertActionNotMounted('revealToken');
    expect($component->instance()->mountedActions)->toBe([]);
});

it('creates a read-only token when no abilities are selected', function () {
    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Read only', 'abilities' => []]);

    $token = $this->user->tokens()->sole();

    expect($token->abilities)->toBe([])
        ->and($token->can('tasks:write'))->toBeFalse()
        ->and($token->can('quotes:write'))->toBeFalse()
        ->and($token->can('invoices:write'))->toBeFalse();
});

it('validates the token name', function (array $data, string $error) {
    $this->user->createToken('Existing');

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', $data)
        ->assertHasActionErrors(['name' => $error]);

    expect($this->user->tokens()->count())->toBe(1);
})->with([
    'required' => [['name' => ''], 'required'],
    'too long' => [['name' => str_repeat('a', 256)], 'max'],
    'duplicate' => [['name' => 'Existing'], 'unique'],
]);

it('allows the same token name for different users', function () {
    User::factory()->create()->createToken('Shared');

    Livewire::test(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Shared', 'abilities' => []]);

    expect($this->user->tokens()->count())->toBe(1);
});

it('revokes an own token', function () {
    $token = $this->user->createToken('Old')->accessToken;

    Livewire::test(ApiTokens::class)
        ->callAction(TestAction::make('revoke')->table($token));

    expect($this->user->tokens()->count())->toBe(0);
});

it('cannot revoke another user token', function () {
    $other = User::factory()->create();
    $token = $other->createToken('Theirs')->accessToken;

    try {
        Livewire::test(ApiTokens::class)
            ->callAction(TestAction::make('revoke')->table($token));
    } catch (Throwable) {
        // Filament refuses records outside the user's own token query.
    }

    expect($other->tokens()->count())->toBe(1);
});

it('shows the MCP endpoint and connect snippet', function () {
    Livewire::test(ApiTokens::class)
        ->assertSee(url('/mcp'))
        ->assertSee('claude mcp add --transport http billing', false);
});

it('redirects guests away from the page', function () {
    auth()->logout();

    $this->get(ApiTokens::getUrl(tenant: $this->account))->assertRedirect();
});
