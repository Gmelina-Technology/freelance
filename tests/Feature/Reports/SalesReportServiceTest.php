<?php

use App\Models\Account;
use App\Models\Sale;
use App\Services\Reports\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->account = Account::factory()->create();
    $this->service = app(SalesReportService::class);
});

function recordSale(Account $account, string $at, float $amount): Sale
{
    return Sale::factory()->for($account)->create(['transaction_date' => $at, 'amount' => $amount]);
}

it('buckets sales into calendar months including the boundary rows', function () {
    recordSale($this->account, '2026-01-01 00:00:00', 10);
    recordSale($this->account, '2026-03-31 23:59:59', 20);
    recordSale($this->account, '2026-04-01 00:00:00', 40);
    recordSale($this->account, '2026-12-31 23:59:59', 80);
    recordSale($this->account, '2025-12-31 23:59:59', 1000);
    recordSale($this->account, '2027-01-01 00:00:00', 2000);

    $totals = $this->service->monthlyTotals($this->account, 2026);

    expect(array_keys($totals))->toBe(range(1, 12))
        ->and($totals[1])->toEqualWithDelta(10, 0.001)
        ->and($totals[3])->toEqualWithDelta(20, 0.001)
        ->and($totals[4])->toEqualWithDelta(40, 0.001)
        ->and($totals[12])->toEqualWithDelta(80, 0.001)
        ->and(array_sum($totals))->toEqualWithDelta(150, 0.001);
});

it('makes each quarter the sum of its three months', function () {
    recordSale($this->account, '2026-01-01 00:00:00', 10);
    recordSale($this->account, '2026-03-31 23:59:59', 20);
    recordSale($this->account, '2026-04-01 00:00:00', 40);
    recordSale($this->account, '2026-08-10 12:00:00', 5.55);
    recordSale($this->account, '2026-12-31 23:59:59', 80);
    recordSale($this->account, '2027-01-01 00:00:00', 2000);

    $months = $this->service->monthlyTotals($this->account, 2026);
    $quarters = $this->service->quarterlyTotals($this->account, 2026);

    expect(array_keys($quarters))->toBe([1, 2, 3, 4]);

    foreach ($quarters as $quarter => $total) {
        $monthsOfQuarter = array_slice($months, ($quarter - 1) * 3, 3);

        expect($total)->toEqualWithDelta(array_sum($monthsOfQuarter), 0.001);
    }

    expect($quarters[1])->toEqualWithDelta(30, 0.001)
        ->and($quarters[2])->toEqualWithDelta(40, 0.001)
        ->and($quarters[3])->toEqualWithDelta(5.55, 0.001)
        ->and($quarters[4])->toEqualWithDelta(80, 0.001);
});

it('runs yearly totals from the first sale year to the current year with zero for gap years', function () {
    recordSale($this->account, '2024-05-01 09:00:00', 100);
    recordSale($this->account, '2026-02-01 09:00:00', 50);

    expect($this->service->yearlyTotals($this->account))->toEqualWithDelta([
        2024 => 100.0,
        2025 => 0.0,
        2026 => 50.0,
    ], 0.001)
        ->and(array_keys($this->service->yearlyTotals($this->account)))->toBe([2024, 2025, 2026]);
});

it('returns only the current year when the account has no sales', function () {
    expect($this->service->yearlyTotals($this->account))->toBe([2026 => 0.0])
        ->and($this->service->availableYears($this->account))->toBe([2026])
        ->and($this->service->firstSaleYear($this->account))->toBeNull();
});

it('lists available years in descending order', function () {
    recordSale($this->account, '2023-07-01 09:00:00', 100);

    expect($this->service->availableYears($this->account))->toBe([2026, 2025, 2024, 2023])
        ->and($this->service->firstSaleYear($this->account))->toBe(2023);
});

it('returns no totals for no buckets without querying', function () {
    DB::enableQueryLog();

    expect($this->service->totalsForBuckets($this->account, []))->toBe([])
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('never counts another account sales', function () {
    $other = Account::factory()->create();
    recordSale($this->account, '2026-02-01 09:00:00', 10);
    recordSale($other, '2026-02-01 09:00:00', 999);
    recordSale($other, '2020-02-01 09:00:00', 999);

    expect($this->service->monthlyTotals($this->account, 2026)[2])->toEqualWithDelta(10, 0.001)
        ->and($this->service->quarterlyTotals($this->account, 2026)[1])->toEqualWithDelta(10, 0.001)
        ->and($this->service->yearlyTotals($this->account))->toEqualWithDelta([2026 => 10.0], 0.001)
        ->and($this->service->availableYears($this->account))->toBe([2026])
        ->and($this->service->query($this->account)->count())->toBe(1);
});

it('runs the same number of queries regardless of sales volume', function (string $method) {
    $small = Account::factory()->create();
    recordSale($small, '2026-02-01 09:00:00', 10);

    $large = Account::factory()->create();
    Sale::factory()->for($large)->count(60)->sequence(
        ['transaction_date' => '2024-03-01 09:00:00'],
        ['transaction_date' => '2025-07-15 09:00:00'],
        ['transaction_date' => '2026-05-20 09:00:00'],
    )->create();

    $count = function (Account $account) use ($method): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $method === 'yearlyTotals'
            ? $this->service->yearlyTotals($account)
            : $this->service->{$method}($account, 2026);

        return count(DB::getQueryLog());
    };

    expect($count($small))->toBe($count($large));
})->with(['monthlyTotals', 'quarterlyTotals', 'yearlyTotals']);
