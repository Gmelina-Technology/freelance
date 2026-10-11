<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function runSalesBackfill(): void
{
    (require database_path('migrations/2026_10_11_104259_backfill_sales_currency_conversion.php'))->up();
}

/**
 * A sale as it looked before this build: no currency, never converted.
 */
function legacySale(Account $account, ?Invoice $invoice = null, float $amount = 100): Sale
{
    return Sale::factory()->for($account)->unconverted()->create([
        'reference_key' => $invoice?->number,
        'amount' => $amount,
    ]);
}

function invoiceForClientIn(Account $account, ?string $clientCurrencyCode): Invoice
{
    $client = Client::factory()->for($account)->create(['currency_code' => $clientCurrencyCode]);

    return Invoice::factory()->for($account)->for($client)->create();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));

    Currency::factory()->create(['code' => 'PHP', 'name' => 'Philippine Peso']);
    $this->account = Account::factory()->withCurrency('EUR')->create();
    $this->accountWithoutCurrency = Account::factory()->create(['currency_code' => null]);
    $this->otherAccount = Account::factory()->withCurrency('EUR')->create();

    $this->sales = [
        'same' => legacySale($this->account, invoiceForClientIn($this->account, 'EUR'), 250),
        'foreign' => legacySale($this->account, invoiceForClientIn($this->account, 'PHP')),
        'unknown' => legacySale($this->account, invoiceForClientIn($this->account, null)),
        'no invoice' => legacySale($this->account),
        'other account invoice' => legacySale($this->account, invoiceForClientIn($this->otherAccount, 'EUR')),
        'account without currency' => legacySale($this->accountWithoutCurrency, invoiceForClientIn($this->accountWithoutCurrency, 'EUR')),
    ];

    DB::table('sales')->update(['updated_at' => '2026-01-01 00:00:00']);
});

it('records the client currency and converts same currency sales at 1:1', function () {
    runSalesBackfill();

    $same = $this->sales['same']->fresh();

    expect($same->currency_code)->toBe('EUR')
        ->and($same->isConverted())->toBeTrue()
        ->and($same->converted_currency_code)->toBe('EUR')
        ->and($same->converted_amount)->toBe('250.00')
        ->and($same->gross_amount)->toBe('250.00')
        ->and($same->exchange_rate)->toBe('1.0000000000')
        ->and($same->fee_amount)->toBeNull()
        ->and($same->converted_by)->toBeNull();
});

it('leaves foreign, unknown and unmatched sales unconverted', function () {
    runSalesBackfill();

    $expectedCurrencies = [
        'foreign' => 'PHP',
        'unknown' => null,
        'no invoice' => null,
        'other account invoice' => null,
        'account without currency' => 'EUR',
    ];

    foreach ($expectedCurrencies as $key => $currencyCode) {
        $sale = $this->sales[$key]->fresh();

        expect($sale->currency_code)->toBe($currencyCode, $key)
            ->and($sale->isConverted())->toBeFalse($key);
    }
});

it('keeps the conversion columns all set or all null', function () {
    runSalesBackfill();

    foreach (Sale::all() as $sale) {
        $nulls = collect(['converted_at', 'converted_currency_code', 'converted_amount', 'gross_amount'])
            ->map(fn (string $column): bool => $sale->{$column} === null)
            ->unique();

        expect($nulls)->toHaveCount(1);
    }
});

it('changes nothing when run again and never touches updated_at', function () {
    runSalesBackfill();
    $afterFirstRun = DB::table('sales')->orderBy('id')->get();

    $this->travel(1)->day();
    runSalesBackfill();

    expect(DB::table('sales')->orderBy('id')->get())->toEqual($afterFirstRun)
        ->and(DB::table('sales')->pluck('updated_at')->unique()->all())->toBe(['2026-01-01 00:00:00']);
});
