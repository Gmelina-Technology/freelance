<?php

use App\Enums\AccountRole;
use App\Filament\App\Resources\Quotes\Pages\ListQuotes;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Services\QuoteService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('only lists the current tenant quotes', function () {
    $a = makeQuoteScenario();
    $b = makeQuoteScenario();

    bindFilamentTenant($a['owner'], $a['account']);

    // Tenant scoping is registered when the panel boots, which Livewire tests skip.
    Filament::getPanel('app')->boot();

    Livewire::test(ListQuotes::class)
        ->assertCanSeeTableRecords([$a['quote']])
        ->assertCanNotSeeTableRecords([$b['quote']]);
});

it('allows owners and managers but not members to access quotes', function (?AccountRole $role) {
    $scenario = makeQuoteScenario();
    $user = $scenario['owner'];

    if ($role) {
        $user = User::factory()->create();
        $scenario['account']->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    bindFilamentTenant($user, $scenario['account']);

    expect(QuoteResource::canAccess())->toBe($role !== AccountRole::Member);
})->with([
    'owner' => [null],
    'manager' => [AccountRole::Manager],
    'member' => [AccountRole::Member],
]);

it('creates tasks in the quote own account even when another tenant is bound', function () {
    $a = makeQuoteScenario();
    $b = makeQuoteScenario();

    // Only the tenant is bound (no active panel), as in a job or console context.
    Filament::setTenant($a['account'], isQuiet: true);

    app(QuoteService::class)->accept($b['quote']);

    expect(Task::withoutGlobalScopes()->where('account_id', $b['account']->id)->count())->toBe(3)
        ->and(Task::withoutGlobalScopes()->where('account_id', $a['account']->id)->count())->toBe(0)
        ->and(Quote::withoutGlobalScopes()->find($b['quote']->id)->status->value)->toBe('accepted');
});
