<?php

use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Pages\CreateQuote;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createQuoteFixture(): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();
    $client = Client::factory()->for($account)->create();
    $project = Project::factory()->for($account)->for($client, 'client')->create();
    $unit = Unit::factory()->for($account)->create();

    bindFilamentTenant($owner, $account);

    return compact('account', 'client', 'project', 'unit');
}

it('persists the quote and its items and totals the amount', function () {
    ['account' => $account, 'client' => $client, 'project' => $project, 'unit' => $unit] = createQuoteFixture();

    Livewire::test(CreateQuote::class)
        ->fillForm([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'items' => [
                ['title' => 'Design', 'unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 100],
                ['title' => 'Build', 'unit_id' => $unit->id, 'quantity' => 1.5, 'unit_price' => 50],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::withoutGlobalScopes()->firstOrFail();

    expect($quote->account_id)->toBe($account->id)
        ->and($quote->project_id)->toBe($project->id)
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->number)->toStartWith('Q')
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->amount)->toEqual('275.00')
        ->and($quote->items->last()->quantity)->toEqual('1.50');
});

it('requires a project and at least one item', function () {
    ['client' => $client] = createQuoteFixture();

    Livewire::test(CreateQuote::class)
        ->fillForm([
            'client_id' => $client->id,
            'project_id' => null,
            'items' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['project_id', 'items']);

    expect(Quote::withoutGlobalScopes()->count())->toBe(0);
});
