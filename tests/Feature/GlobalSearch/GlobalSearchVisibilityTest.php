<?php

use App\Enums\AccountRole;
use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Filament\App\Clusters\Settings\Resources\BankDetails\BankDetailsResource;
use App\Filament\App\Clusters\Settings\Resources\Categories\CategoryResource;
use App\Filament\App\Clusters\Settings\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\App\Clusters\Settings\Resources\Invitations\InvitationResource;
use App\Filament\App\Clusters\Settings\Resources\Members\MemberResource;
use App\Filament\App\Clusters\Settings\Resources\Units\UnitResource;
use App\Filament\App\Resources\Clients\ClientResource;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Filament\App\Resources\Projects\Pages\ListProjects;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Filament\App\Resources\Projects\Resources\Tasks\TaskResource as ProjectTaskResource;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Filament\App\Resources\Sales\SaleResource;
use App\Filament\App\Resources\Tasks\Pages\ListTasks;
use App\Filament\App\Resources\Tasks\TaskResource;
use App\Models\Account;
use App\Models\BankDetail;
use App\Models\Category;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Titles of every global search result for the term, across all resource categories.
 *
 * @return Collection<int, string>
 */
function globalSearchTitles(string $term): Collection
{
    return Filament::getGlobalSearchProvider()->getResults($term)
        ->getCategories()
        ->flatten()
        ->map(fn ($result): string => $result->title);
}

/**
 * Create the Zephyr client, project, invoice, quote and task for an account, tagged with a suffix.
 */
function makeZephyrRecords(Account $account, string $suffix): void
{
    $client = Client::factory()->create(['account_id' => $account->id, 'name' => "Zephyr {$suffix}"]);
    $project = Project::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'name' => "Zephyr {$suffix} project",
    ]);
    Invoice::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'number' => "INV-{$suffix}",
    ]);
    Quote::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'number' => "QTE-{$suffix}",
    ]);
    Task::factory()->create([
        'account_id' => $account->id,
        'project_id' => $project->id,
        'title' => "Zephyr {$suffix} task",
    ]);
}

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->owner = makeAccountUser($this->account, AccountRole::Owner);
    $this->manager = makeAccountUser($this->account, AccountRole::Manager);
    $this->member = makeAccountUser($this->account, AccountRole::Member);

    // Created before the tenant is bound, otherwise the tenancy observer reassigns them.
    $otherAccount = Account::factory()->create();
    makeZephyrRecords($otherAccount, 'Bravo');

    bindFilamentTenant($this->owner, $this->account);
});

it('shows members only the tasks assigned to them', function () {
    $own = Task::factory()->create([
        'account_id' => $this->account->id,
        'title' => 'Zephyr A',
        'assigned_user_id' => $this->member->id,
    ]);
    Task::factory()->create([
        'account_id' => $this->account->id,
        'title' => 'Zephyr B',
        'assigned_user_id' => $this->owner->id,
    ]);

    bindFilamentTenant($this->member, $this->account);

    expect(TaskResource::getGlobalSearchResults('Zephyr')->pluck('title')->all())->toBe(['Zephyr A']);
});

it('shows owners and managers every matching task and project', function (string $role) {
    $user = $this->{$role};
    Task::factory()->count(2)->sequence(['title' => 'Zephyr A'], ['title' => 'Zephyr B'])->create([
        'account_id' => $this->account->id,
        'assigned_user_id' => $this->member->id,
    ]);
    $client = Client::factory()->create(['account_id' => $this->account->id]);
    Project::factory()->count(2)->sequence(['name' => 'Zephyr P1'], ['name' => 'Zephyr P2'])->create([
        'account_id' => $this->account->id,
        'client_id' => $client->id,
    ]);

    bindFilamentTenant($user, $this->account);

    expect(TaskResource::getGlobalSearchResults('Zephyr')->pluck('title')->sort()->values()->all())
        ->toBe(['Zephyr A', 'Zephyr B'])
        ->and(ProjectResource::getGlobalSearchResults('Zephyr P')->pluck('title')->sort()->values()->all())
        ->toBe(['Zephyr P1', 'Zephyr P2']);
})->with(['owner', 'manager']);

it('shows members only the projects they are assigned to', function () {
    $client = Client::factory()->create(['account_id' => $this->account->id]);
    $assigned = Project::factory()->create(['account_id' => $this->account->id, 'client_id' => $client->id, 'name' => 'Zephyr P1']);
    Project::factory()->create(['account_id' => $this->account->id, 'client_id' => $client->id, 'name' => 'Zephyr P2']);
    $assigned->assignees()->attach($this->member);

    bindFilamentTenant($this->member, $this->account);

    expect(ProjectResource::getGlobalSearchResults('Zephyr')->pluck('title')->all())->toBe(['Zephyr P1']);
});

it('keeps the member task and project lists in line with search', function () {
    $client = Client::factory()->create(['account_id' => $this->account->id]);
    $ownTask = Task::factory()->create(['account_id' => $this->account->id, 'status' => TaskStatus::OPEN, 'assigned_user_id' => $this->member->id]);
    $otherTask = Task::factory()->create(['account_id' => $this->account->id, 'status' => TaskStatus::OPEN, 'assigned_user_id' => $this->owner->id]);
    $assigned = Project::factory()->create(['account_id' => $this->account->id, 'client_id' => $client->id]);
    $unassigned = Project::factory()->create(['account_id' => $this->account->id, 'client_id' => $client->id]);
    $assigned->assignees()->attach($this->member);

    bindFilamentTenant($this->member, $this->account);

    Livewire::test(ListTasks::class)
        ->assertCanSeeTableRecords([$ownTask])
        ->assertCanNotSeeTableRecords([$otherTask]);
    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$assigned])
        ->assertCanNotSeeTableRecords([$unassigned]);

    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ListTasks::class)->assertCanSeeTableRecords([$ownTask, $otherTask]);
    Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$assigned, $unassigned]);
});

it('limits which resources each role can search', function (string $role, bool $canSearchBilling, bool $canSearchClients) {
    bindFilamentTenant($this->{$role}, $this->account);

    expect(InvoiceResource::canGloballySearch())->toBe($canSearchBilling)
        ->and(QuoteResource::canGloballySearch())->toBe($canSearchBilling)
        ->and(ClientResource::canGloballySearch())->toBe($canSearchClients);
})->with([
    'owner' => ['owner', true, true],
    'manager' => ['manager', true, false],
    'member' => ['member', false, false],
]);

it('does not search sales, settings or nested project tasks for any role', function (string $role, string $resource) {
    bindFilamentTenant($this->{$role}, $this->account);

    expect($resource::canGloballySearch())->toBeFalse();
})->with(['owner', 'manager', 'member'])->with([
    BankDetailsResource::class,
    CategoryResource::class,
    InvitationResource::class,
    MemberResource::class,
    UnitResource::class,
    EmailTemplateResource::class,
    SaleResource::class,
    ProjectTaskResource::class,
]);

it('returns no billing or client categories for members', function () {
    makeZephyrRecords($this->account, 'Alpha');
    $sale = Sale::factory()->create(['account_id' => $this->account->id]);

    expect(Client::query()->where('name', 'like', '%Zephyr%')->exists())->toBeTrue()
        ->and(Invoice::query()->where('number', 'INV-Alpha')->exists())->toBeTrue()
        ->and(Quote::query()->where('number', 'QTE-Alpha')->exists())->toBeTrue();

    bindFilamentTenant($this->owner, $this->account);
    $ownerKeys = Filament::getGlobalSearchProvider()->getResults('Zephyr')->getCategories()->keys()->all();

    expect($ownerKeys)->toContain(InvoiceResource::getPluralModelLabel())
        ->toContain(QuoteResource::getPluralModelLabel())
        ->toContain(ClientResource::getPluralModelLabel());

    bindFilamentTenant($this->member, $this->account);
    $memberKeys = Filament::getGlobalSearchProvider()->getResults('Zephyr')->getCategories()->keys()->all();
    $memberSaleKeys = Filament::getGlobalSearchProvider()->getResults((string) $sale->id)->getCategories()->keys()->all();

    expect($memberKeys)->not->toContain(InvoiceResource::getPluralModelLabel())
        ->not->toContain(QuoteResource::getPluralModelLabel())
        ->not->toContain(ClientResource::getPluralModelLabel())
        ->and($memberSaleKeys)->not->toContain(SaleResource::getPluralModelLabel());
});

it('never returns settings records or sales from a provider search', function (string $role) {
    Category::factory()->create(['account_id' => $this->account->id, 'name' => 'Zephyr category']);
    Unit::factory()->create(['account_id' => $this->account->id, 'name' => 'Zephyr unit']);
    BankDetail::factory()->create([
        'account_id' => $this->account->id,
        'bank_name' => 'Zephyr Bank',
        'bsb' => '987-654',
        'swift_bic_code' => 'ZEPHAU2X',
        'account_number' => '5550001234',
    ]);
    $invitation = Invitation::create([
        'account_id' => $this->account->id,
        'email' => 'zephyr-invitee@example.test',
        'role' => AccountRole::Member,
        'token' => 'zephyr-secret-invite-token',
    ]);
    $person = makeAccountUser($this->account, AccountRole::Member);
    $person->update(['name' => 'Zephyr Person']);
    $sale = Sale::factory()->create(['account_id' => $this->account->id]);

    // Each excluded resource has a record that would match if it were searchable.
    expect(Category::query()->where('name', 'like', '%Zephyr%')->exists())->toBeTrue()
        ->and(Unit::query()->where('name', 'like', '%Zephyr%')->exists())->toBeTrue()
        ->and(BankDetail::query()->where('bank_name', 'like', '%Zephyr%')->exists())->toBeTrue()
        ->and($invitation->exists)->toBeTrue()
        ->and(User::query()->where('name', 'like', '%Zephyr%')->exists())->toBeTrue()
        ->and(Sale::query()->whereKey($sale->id)->exists())->toBeTrue();

    bindFilamentTenant($this->{$role}, $this->account);

    $provider = Filament::getGlobalSearchProvider();
    $zephyr = $provider->getResults('Zephyr');
    $saleKeys = $provider->getResults((string) $sale->id)->getCategories()->keys()->all();
    $zephyrKeys = $zephyr->getCategories()->keys()->all();

    foreach ([
        CategoryResource::class,
        UnitResource::class,
        BankDetailsResource::class,
        InvitationResource::class,
        MemberResource::class,
        EmailTemplateResource::class,
    ] as $resource) {
        expect($zephyrKeys)->not->toContain($resource::getPluralModelLabel());
    }

    expect($saleKeys)->not->toContain(SaleResource::getPluralModelLabel());

    $serialized = json_encode($zephyr->getCategories()->flatten()->map(fn ($result): array => [
        'title' => $result->title,
        'details' => $result->details,
    ])->all());

    foreach (['987-654', 'ZEPHAU2X', '5550001234', 'zephyr-secret-invite-token'] as $secret) {
        expect($serialized)->not->toContain($secret);
    }
})->with(['owner', 'manager', 'member']);

it('lists a task once, under the top-level tasks category', function () {
    makeZephyrRecords($this->account, 'Alpha');

    $categories = Filament::getGlobalSearchProvider()->getResults('Zephyr Alpha task')->getCategories();

    expect($categories->keys()->all())->toBe([TaskResource::getPluralModelLabel()])
        ->and($categories->flatten())->toHaveCount(1);
});

it('only returns records from the current account', function () {
    makeZephyrRecords($this->account, 'Alpha');

    $titles = globalSearchTitles('Zephyr');

    expect($titles)->toHaveCount(5)
        ->and($titles->filter(fn (string $title): bool => str_contains($title, 'Bravo')))->toBeEmpty();
});

it('renders result details in the global search component', function () {
    Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $client = Client::factory()->create(['account_id' => $this->account->id, 'name' => 'Zephyr Labs', 'currency_code' => 'USD']);
    Invoice::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $client->id,
        'amount' => 1250,
        'status' => InvoiceStatus::Sent,
    ]);
    BankDetail::factory()->create(['account_id' => $this->account->id, 'bank_name' => 'Zephyr Savings Bank']);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'Zephyr')
        ->assertSee('Zephyr Labs')
        ->assertSee('USD 1,250.00')
        ->assertDontSee('Zephyr Savings Bank');
});
