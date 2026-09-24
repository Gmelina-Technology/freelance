<?php

use App\Enums\AccountRole;
use App\Enums\InvoiceStatus;
use App\Filament\App\Clusters\Reports\Pages\ClientRevenueReportPage;
use App\Filament\App\Clusters\Reports\Pages\ReceivablesReportPage;
use App\Filament\App\Clusters\Reports\Pages\SalesReportPage;
use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Filament\App\Clusters\Reports\Widgets\SalesByMonthChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByQuarterChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByYearChart;
use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

/**
 * One client with one project, a sent invoice and a paid invoice with its sale.
 */
function seedReportTenant(Account $account, string $clientName, string $projectName, float $amount, string $at = '2026-03-01 10:00:00'): void
{
    $client = Client::factory()->for($account)->create(['name' => $clientName]);
    $project = Project::factory()->for($account)->for($client)->create(['name' => $projectName]);

    $paid = Invoice::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'amount' => $amount,
        'status' => InvoiceStatus::Paid,
    ]);
    Sale::factory()->forInvoice($paid)->create(['transaction_date' => $at]);

    Invoice::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'amount' => $amount / 2,
        'status' => InvoiceStatus::Sent,
    ]);
}

it('lets owners and managers into reports but not members', function (?AccountRole $role) {
    $user = $role ? makeAccountUser($this->account, $role) : $this->owner;

    bindFilamentTenant($user, $this->account);

    $allowed = $role !== AccountRole::Member;

    expect(ReportsCluster::canAccess())->toBe($allowed)
        ->and(SalesReportPage::canAccess())->toBe($allowed)
        ->and(ClientRevenueReportPage::canAccess())->toBe($allowed)
        ->and(ReceivablesReportPage::canAccess())->toBe($allowed)
        ->and(SalesByMonthChart::canView())->toBe($allowed)
        ->and(SalesByQuarterChart::canView())->toBe($allowed)
        ->and(SalesByYearChart::canView())->toBe($allowed)
        ->and(ReportsCluster::shouldRegisterNavigation())->toBe($allowed);
})->with([
    'owner by pivot' => [AccountRole::Owner],
    'manager' => [AccountRole::Manager],
    'member' => [AccountRole::Member],
    'owner by owner_id, no pivot' => [null],
]);

it('forbids members from every report page', function (string $page) {
    bindFilamentTenant(makeAccountUser($this->account, AccountRole::Member), $this->account);

    Livewire::test($page)->assertForbidden();
})->with([
    'sales' => SalesReportPage::class,
    'client revenue' => ClientRevenueReportPage::class,
    'receivables' => ReceivablesReportPage::class,
]);

it('forbids members from the sales chart widgets', function (string $widget) {
    bindFilamentTenant($this->owner, $this->account);
    $component = Livewire::test($widget);

    bindFilamentTenant(makeAccountUser($this->account, AccountRole::Member), $this->account);

    $component->set('filter', '2026')->assertForbidden();
})->with([
    'monthly' => SalesByMonthChart::class,
    'quarterly' => SalesByQuarterChart::class,
    'yearly' => SalesByYearChart::class,
]);

describe('rendered pages', function () {
    beforeEach(function () {
        seedReportTenant($this->account, 'Acme Holdings', 'Aurora Website', 1234);
        seedReportTenant(Account::factory()->create(), 'Rival Industries', 'Nebula Portal', 8888);

        bindFilamentTenant($this->owner, $this->account);
    });

    it('shows only the tenant clients and projects', function (string $page, array $figures) {
        seedReportTenant($this->account, 'Borealis Labs', 'Cobalt App', 300);

        Livewire::test($page)
            ->assertOk()
            ->assertSee(['Acme Holdings', 'Aurora Website', 'Borealis Labs', 'Cobalt App', ...$figures])
            ->assertDontSee(['Rival Industries', 'Nebula Portal', '8,888', '4,444']);
    })->with([
        'client revenue' => [ClientRevenueReportPage::class, ['$1,234.00', '$300.00']],
        'receivables' => [ReceivablesReportPage::class, ['$617.00', '$1,234.00', '$150.00', '$300.00']],
    ]);

    it('shows only the tenant sales on the sales page', function () {
        Livewire::test(SalesReportPage::class)->assertOk();

        foreach ([SalesByMonthChart::class, SalesByQuarterChart::class, SalesByYearChart::class] as $widget) {
            Livewire::test($widget)
                ->assertSee('$1,234.00')
                ->assertDontSee('8,888')
                ->assertDontSee('10,122');
        }
    });

    it('narrows client revenue to the selected year', function () {
        $client = Client::query()->where('name', 'Acme Holdings')->first();
        $oldProject = Project::factory()->for($this->account)->for($client)->create(['name' => 'Legacy Intranet']);
        $invoice = Invoice::factory()->create([
            'account_id' => $this->account->id,
            'client_id' => $client->id,
            'project_id' => $oldProject->id,
            'amount' => 500,
            'status' => InvoiceStatus::Paid,
        ]);
        Sale::factory()->forInvoice($invoice)->create(['transaction_date' => '2025-05-01 10:00:00']);

        Livewire::test(ClientRevenueReportPage::class)
            ->assertSee(['Aurora Website', 'Legacy Intranet'])
            ->set('filters.year', 2025)
            ->assertSee('Legacy Intranet')
            ->assertDontSee('Aurora Website')
            ->set('filters.year', 2026)
            ->assertSee('Aurora Website')
            ->assertDontSee('Legacy Intranet');
    });

    it('treats an unknown revenue year as all time', function (mixed $year) {
        Livewire::test(ClientRevenueReportPage::class)
            ->set('filters.year', $year)
            ->assertOk()
            ->assertSee(['Aurora Website', '$1,234.00']);
    })->with(['abc', 1900, 'another tenant year' => 2019]);

    it('explains the receivables figures', function () {
        Livewire::test(ReceivablesReportPage::class)
            ->assertSee('Pending covers every outstanding invoice (Sent and Overdue)')
            ->assertSee('Owed equals Pending')
            ->assertSee('is the part of Pending with status Overdue, plus Sent invoices whose due date is before today')
            ->assertSee('Collected (Paid) is shown separately and is not owed')
            ->assertSee('Of which overdue')
            ->assertSee('Owed (pending)')
            ->assertDontSee('appear under Pending');
    });

    it('shows figures in the client currency', function () {
        Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
        Client::query()->where('name', 'Acme Holdings')->update(['currency_code' => 'EUR']);

        Livewire::test(ClientRevenueReportPage::class)->assertSee('€1,234.00')->assertDontSee('$1,234.00');
        Livewire::test(ReceivablesReportPage::class)->assertSee(['€617.00', '€1,234.00']);
    });

    it('shows unattributed sales in the account currency', function () {
        Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
        $this->account->update(['currency_code' => 'EUR']);
        Sale::factory()->for($this->account)->create(['amount' => 42, 'transaction_date' => '2026-02-01 10:00:00']);

        Livewire::test(ClientRevenueReportPage::class)
            ->assertSee('Unattributed sales')
            ->assertSee('€42.00');
    });

    it('queries the report data a bounded number of times per render', function (string $page, string $table, int $limit) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test($page);

        $reportQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], "from \"{$table}\""))
            ->count();

        expect($reportQueries)->toBeLessThanOrEqual($limit);
    })->with([
        'client revenue' => [ClientRevenueReportPage::class, 'sales', 2],
        'receivables' => [ReceivablesReportPage::class, 'invoices', 1],
    ]);
});

it('shows empty states when there is nothing to report', function () {
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ClientRevenueReportPage::class)->assertSee('No sales recorded for this period.');
    Livewire::test(ReceivablesReportPage::class)->assertSee('No outstanding or collected invoices.');
});
