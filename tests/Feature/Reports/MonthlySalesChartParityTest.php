<?php

use App\Enums\AccountRole;
use App\Filament\App\Widgets\MonthlySalesChart;
use App\Models\Account;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->account->users()->attach($this->owner, ['role' => AccountRole::Owner->value]);
    $this->otherAccount = Account::factory()->create();

    foreach ([
        ['2026-01-01 00:00:00', 10.10],
        ['2026-01-31 23:59:59', 20.20],
        ['2026-02-01 00:00:00', 30.30],
        ['2026-03-31 23:59:59', 40.40],
        ['2026-04-01 00:00:00', 50.50],
        ['2026-06-15 09:59:59', 60.60],
        ['2026-12-31 23:59:59', 70.70],
        ['2025-12-31 23:59:59', 1000],
        ['2027-01-01 00:00:00', 2000],
    ] as [$at, $amount]) {
        Sale::factory()->for($this->account)->create(['transaction_date' => $at, 'amount' => $amount]);
    }

    Sale::factory()->for($this->otherAccount)->create(['transaction_date' => '2026-01-15 12:00:00', 'amount' => 5000]);
    Sale::factory()->for($this->otherAccount)->create(['transaction_date' => '2026-06-01 12:00:00', 'amount' => 5000]);

    bindFilamentTenant($this->owner, $this->account);
});

/**
 * The widget's original per-month query, kept verbatim as the parity oracle.
 *
 * @return array{data: list<mixed>, labels: list<string>}
 */
function monthlySalesOracle(): array
{
    $salesData = [];
    $labels = [];

    for ($month = 1; $month <= 12; $month++) {

        $total = Sale::query()
            ->where('account_id', Filament::getTenant()->id)
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');

        $salesData[] = $total;

        $labels[] = date('M', mktime(0, 0, 0, $month, 1));
    }

    return ['data' => $salesData, 'labels' => $labels];
}

/**
 * @return array<string, mixed>
 */
function monthlySalesChartData(): array
{
    return (fn (): array => $this->getData())->call(new MonthlySalesChart);
}

it('matches the original per-month query output', function () {
    $oracle = monthlySalesOracle();
    $data = monthlySalesChartData();

    expect($data['labels'])->toBe($oracle['labels'])
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(12);

    foreach ($oracle['data'] as $index => $expected) {
        expect((float) $data['datasets'][0]['data'][$index])->toEqualWithDelta((float) $expected, 0.001);
    }

    expect(array_sum(array_map('floatval', $oracle['data'])))->toEqualWithDelta(282.80, 0.001);
});

it('loads the whole year with a single query', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    monthlySalesChartData();

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('keeps the dataset presentation and widget settings', function () {
    $widget = new MonthlySalesChart;
    $data = monthlySalesChartData();

    expect($data['datasets'][0])->toMatchArray([
        'label' => 'Sales',
        'backgroundColor' => '#36A2EB',
        'borderColor' => '#36A2EB',
    ])
        ->and($widget->getHeading())->toBe('Monthly Sales')
        ->and(MonthlySalesChart::getSort())->toBe(2)
        ->and($widget->getColumns())->toBe(12)
        ->and((fn (): string => $this->getType())->call($widget))->toBe('bar');
});

it('is visible to owners and managers but not members', function (?AccountRole $role) {
    $user = $role ? makeAccountUser($this->account, $role) : $this->owner;

    bindFilamentTenant($user, $this->account);

    expect(MonthlySalesChart::canView())->toBe($role !== AccountRole::Member);
})->with([
    'owner' => [null],
    'manager' => [AccountRole::Manager],
    'member' => [AccountRole::Member],
]);

it('leaves the report widgets off the dashboard', function () {
    expect(Filament::getPanel('app')->getWidgets())
        ->toContain(MonthlySalesChart::class)
        ->not->toContain('App\Filament\App\Clusters\Reports\Widgets\SalesByMonthChart')
        ->not->toContain('App\Filament\App\Clusters\Reports\Widgets\SalesByQuarterChart')
        ->not->toContain('App\Filament\App\Clusters\Reports\Widgets\SalesByYearChart');
});
