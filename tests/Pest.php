<?php

use App\Enums\AccountRole;
use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build an account with an owner, a client, a project and a unit, plus a quote with line items.
 *
 * @param  array<int, array<string, mixed>>  $lines  quote line attributes; defaults to three lines
 * @return array{account: Account, owner: User, client: Client, project: Project, unit: Unit, quote: Quote}
 */
function makeQuoteScenario(array $lines = [], QuoteStatus $status = QuoteStatus::Sent): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();
    $client = Client::factory()->for($account)->create();
    $project = Project::factory()->for($account)->for($client, 'client')->create();
    $unit = Unit::factory()->for($account)->create();
    $quote = Quote::factory()->forProject($project)->create(['status' => $status]);

    $lines = $lines ?: [
        ['title' => 'Design', 'quantity' => 2, 'unit_price' => 100],
        ['title' => 'Build', 'quantity' => 7.5, 'unit_price' => 80],
        ['title' => 'Launch', 'quantity' => 1, 'unit_price' => 50],
    ];

    foreach (array_values($lines) as $index => $line) {
        QuoteItem::factory()->for($quote)->create([
            'unit_id' => $unit->id,
            'sort_order' => $index,
            ...$line,
        ]);
    }

    $quote->recalculateAmount();

    return compact('account', 'owner', 'client', 'project', 'unit', 'quote');
}

/**
 * Bind the Filament tenant and acting user for panel tests.
 */
function bindFilamentTenant(User $user, Account $account): void
{
    Livewire\Livewire::actingAs($user);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    // Tenant scoping and ownership are registered when the panel boots, which Livewire tests skip.
    Filament\Facades\Filament::getPanel('app')->boot();
    Filament\Facades\Filament::setTenant($account);
}

/**
 * Attach a new user to the account with the given role.
 */
function makeAccountUser(Account $account, AccountRole $role): User
{
    $user = User::factory()->create();
    $account->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);

    return $user;
}
