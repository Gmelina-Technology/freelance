<?php

use App\Filament\App\Clusters\Reports\Widgets\SalesByMonthChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByQuarterChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByYearChart;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create(['currency_code' => null]);

    foreach ([
        ['2025-02-10 09:00:00', 100],
        ['2025-11-10 09:00:00', 200],
        ['2026-01-10 09:00:00', 30],
        ['2026-05-10 09:00:00', 40],
    ] as [$at, $amount]) {
        Sale::factory()->for($this->account)->create(['transaction_date' => $at, 'amount' => $amount]);
    }

    // Another tenant with a year this account never sold in.
    Sale::factory()->for(Account::factory())->create(['transaction_date' => '2019-03-01 09:00:00', 'amount' => 9999]);

    bindFilamentTenant($this->owner, $this->account);

    $this->service = app(SalesReportService::class);
});

/**
 * @return list<float>
 */
function chartValues(Testable $component): array
{
    return (fn (): array => $this->getCachedData())->call($component->instance())['datasets'][0]['data'];
}

it('defaults the year filter to the current year', function (string $widget, string $method) {
    $component = Livewire::test($widget)->assertSet('filter', '2026');

    expect(chartValues($component))->toEqualWithDelta(array_values($this->service->{$method}($this->account, 2026)), 0.001)
        ->and($component->instance()->getDescription())->toBe('Total for 2026: $70.00');
})->with([
    'monthly' => [SalesByMonthChart::class, 'monthlyTotals'],
    'quarterly' => [SalesByQuarterChart::class, 'quarterlyTotals'],
]);

it('switches the data when another year is selected', function (string $widget, string $method) {
    $component = Livewire::test($widget)->set('filter', '2025');

    expect(chartValues($component))->toEqualWithDelta(array_values($this->service->{$method}($this->account, 2025)), 0.001)
        ->and(array_sum(chartValues($component)))->toEqualWithDelta(300, 0.001)
        ->and($component->instance()->getDescription())->toBe('Total for 2025: $300.00');
})->with([
    'monthly' => [SalesByMonthChart::class, 'monthlyTotals'],
    'quarterly' => [SalesByQuarterChart::class, 'quarterlyTotals'],
]);

it('falls back to the current year for a tampered filter', function (string $widget, string $filter) {
    $component = Livewire::test($widget)->set('filter', $filter)->assertOk();

    expect(array_sum(chartValues($component)))->toEqualWithDelta(70, 0.001)
        ->and($component->instance()->getDescription())->toBe('Total for 2026: $70.00');
})->with(['monthly' => SalesByMonthChart::class, 'quarterly' => SalesByQuarterChart::class])
    ->with(['abc', '1900', 'another tenant year' => '2019']);

it('labels months and quarters', function () {
    $monthly = (fn (): array => $this->getCachedData())->call(Livewire::test(SalesByMonthChart::class)->instance());
    $quarterly = (fn (): array => $this->getCachedData())->call(Livewire::test(SalesByQuarterChart::class)->instance());

    expect($monthly['labels'])->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])
        ->and($quarterly['labels'])->toBe(['Q1', 'Q2', 'Q3', 'Q4']);
});

it('offers only this account years in the filter', function () {
    $filters = (fn (): array => $this->getFilters())->call(Livewire::test(SalesByMonthChart::class)->instance());

    expect($filters)->toBe([2026 => '2026', 2025 => '2025']);
});

it('shows yearly totals from the first sale year', function () {
    $component = Livewire::test(SalesByYearChart::class);
    $data = (fn (): array => $this->getCachedData())->call($component->instance());

    expect($data['labels'])->toBe(['2025', '2026'])
        ->and(chartValues($component))->toEqualWithDelta(array_values($this->service->yearlyTotals($this->account)), 0.001)
        ->and($component->instance()->getDescription())->toBe('All-time total: $370.00');
});

it('formats totals in the account currency', function () {
    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $this->account->update(['currency_code' => 'EUR']);

    expect(Livewire::test(SalesByYearChart::class)->instance()->getDescription())->toBe('All-time total: €370.00');
});

it('renders the three charts with at most six report queries', function () {
    $reportQueries = 0;

    foreach ([SalesByMonthChart::class, SalesByQuarterChart::class, SalesByYearChart::class] as $widget) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test($widget);

        $reportQueries += collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "sales"'))
            ->count();
    }

    expect($reportQueries)->toBeLessThanOrEqual(6);
});
