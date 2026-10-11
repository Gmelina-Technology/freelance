<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\SaleConversion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function runSalesBackfill(): void
{
    (require database_path('migrations/2026_10_11_104259_backfill_sales_currency_code.php'))->up();
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

it('records the client currency and converts no sale', function () {
    runSalesBackfill();

    $expectedCurrencies = [
        'same' => 'EUR',
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

    expect(SaleConversion::count())->toBe(0);
});

it('changes nothing when run again and never touches updated_at', function () {
    runSalesBackfill();
    $afterFirstRun = DB::table('sales')->orderBy('id')->get();

    $this->travel(1)->day();
    runSalesBackfill();

    expect(DB::table('sales')->orderBy('id')->get())->toEqual($afterFirstRun)
        ->and(DB::table('sales')->pluck('updated_at')->unique()->all())->toBe(['2026-01-01 00:00:00'])
        ->and(SaleConversion::count())->toBe(0);
});
