<?php

use App\Exceptions\SaleConversionException;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\SaleConversionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));

    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $this->account = Account::factory()->withCurrency('PHP')->create();
    $this->user = User::factory()->create();
    $this->service = app(SaleConversionService::class);
});

function batchSales(Account $account, int $count = 2, ?string $currencyCode = 'EUR', float $amount = 1000): EloquentCollection
{
    return Sale::factory()->for($account)->unconverted($currencyCode)->count($count)->create(['amount' => $amount]);
}

it('converts every sale at the shared rate with fee and tax on each', function () {
    Model::preventLazyLoading();
    $sales = batchSales($this->account, 3);

    $conversions = $this->service->convertBatch($this->account, $sales, [
        'exchange_rate' => '56.5',
        'fee_amount' => '100.25',
        'tax_amount' => '50',
        'conversion_note' => 'Wise payout',
    ], $this->user);

    expect($conversions)->toHaveCount(3)
        ->and(SaleConversion::count())->toBe(3);

    foreach (SaleConversion::all() as $conversion) {
        expect($conversion->gross_amount)->toBe('56500.00')
            ->and($conversion->converted_amount)->toBe('56349.75')
            ->and($conversion->fee_amount)->toBe('100.25')
            ->and($conversion->tax_amount)->toBe('50.00')
            ->and($conversion->exchange_rate)->toBe('56.5000000000')
            ->and($conversion->conversion_note)->toBe('Wise payout')
            ->and($conversion->converted_currency_code)->toBe('PHP')
            ->and($conversion->converted_by)->toBe($this->user->id)
            ->and($conversion->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00');
    }
});

it('blocks the whole batch for every ineligible selection', function (Closure $makeSales, string $message) {
    $sales = $makeSales($this->account);
    $alreadyConverted = SaleConversion::count();

    expect(fn () => $this->service->convertBatch($this->account, $sales, ['exchange_rate' => 2], $this->user))
        ->toThrow(SaleConversionException::class, $message);

    expect(SaleConversion::count())->toBe($alreadyConverted);
})->with([
    'mixed currencies' => [
        fn (Account $account) => batchSales($account, 1)->merge(batchSales($account, 1, 'USD')),
        'different currencies (EUR, USD)',
    ],
    'an already converted sale' => [
        fn (Account $account) => batchSales($account, 1)->push(Sale::factory()->for($account)->converted('PHP', 10)->create()),
        '1 selected sale(s) are already converted',
    ],
    'a sale in the account currency' => [
        fn (Account $account) => batchSales($account, 1)->merge(batchSales($account, 1, 'PHP')),
        '1 selected sale(s) are already in PHP',
    ],
    'a sale with an unknown currency' => [
        fn (Account $account) => batchSales($account, 1)->merge(batchSales($account, 1, null)),
        '1 selected sale(s) have no known currency',
    ],
    'an empty selection' => [
        fn () => new EloquentCollection,
        'Select at least one sale',
    ],
]);

it('leaves an unknown currency and an existing conversion untouched when blocked', function () {
    $unknown = batchSales($this->account, 1, null)->first();
    $existing = Sale::factory()->for($this->account)->converted('PHP', 10)->create();

    expect(fn () => $this->service->convertBatch($this->account, new EloquentCollection([$unknown, $existing]), ['exchange_rate' => 2], $this->user))
        ->toThrow(SaleConversionException::class);

    expect($unknown->fresh()->currency_code)->toBeNull()
        ->and($existing->saleConversion()->sole()->converted_amount)->toBe('10.00');
});

it('refuses when the account has no currency', function () {
    $account = Account::factory()->create(['currency_code' => null]);

    expect(fn () => $this->service->convertBatch($account, batchSales($account), ['exchange_rate' => 2], $this->user))
        ->toThrow(SaleConversionException::class, 'Set your account currency in Account Profile');

    expect(SaleConversion::count())->toBe(0);
});

it('refuses sales that belong to another account', function () {
    $other = Account::factory()->withCurrency('PHP')->create();
    $sales = batchSales($this->account, 1)->merge(batchSales($other, 1));

    expect(fn () => $this->service->convertBatch($this->account, $sales, ['exchange_rate' => 2], $this->user))
        ->toThrow(SaleConversionException::class, "don't belong to this account");

    expect(SaleConversion::count())->toBe(0);
});

it('refuses a batch above the size cap', function () {
    $sales = batchSales($this->account, SaleConversionService::MAX_BATCH_SIZE + 1);

    expect(SaleConversionService::MAX_BATCH_SIZE)->toBe(100)
        ->and(fn () => $this->service->convertBatch($this->account, $sales, ['exchange_rate' => 2], $this->user))
        ->toThrow(SaleConversionException::class, 'at most 100 sales');

    expect(SaleConversion::count())->toBe(0);
});

it('writes nothing when the batch input is invalid', function () {
    expect(fn () => $this->service->convertBatch($this->account, batchSales($this->account), ['exchange_rate' => 0], $this->user))
        ->toThrow(ValidationException::class);

    expect(SaleConversion::count())->toBe(0);
});

it('writes nothing for any sale when one sale breaks a limit', function () {
    $small = Sale::factory()->for($this->account)->unconverted('EUR')->create(['amount' => 10, 'reference_key' => 'INV-SMALL']);
    $sales = batchSales($this->account, 2)->push($small);

    expect(fn () => $this->service->convertBatch($this->account, $sales, ['exchange_rate' => 2, 'fee_amount' => '50'], $this->user))
        ->toThrow(SaleConversionException::class, "Sale INV-SMALL can't be converted at this rate");

    expect(SaleConversion::count())->toBe(0);
});

it('rejects a rate that overflows the limit and still converts right under it', function () {
    $big = Sale::factory()->for($this->account)->unconverted('EUR')->create(['amount' => 10000000]);

    expect(fn () => $this->service->convertBatch($this->account, new EloquentCollection([$big]), ['exchange_rate' => '9999999999999'], $this->user))
        ->toThrow(SaleConversionException::class, "Sale #{$big->id} can't be converted at this rate");

    expect(SaleConversion::count())->toBe(0);

    $edge = Sale::factory()->for($this->account)->unconverted('EUR')->create(['amount' => 1000]);

    $this->service->convertBatch($this->account, new EloquentCollection([$edge]), ['exchange_rate' => '9999999999.99'], $this->user);

    expect($edge->saleConversion()->sole()->gross_amount)->toBe('9999999999990.00');
});

it('ignores keys outside the batch input', function () {
    $other = Account::factory()->withCurrency('EUR')->create();

    $this->service->convertBatch($this->account, batchSales($this->account, 1, 'EUR', 100), [
        'exchange_rate' => 2,
        'converted_amount' => 1,
        'converted_by' => User::factory()->create()->id,
        'converted_currency_code' => 'EUR',
        'currency_code' => 'USD',
        'account_id' => $other->id,
    ], $this->user);

    $conversion = SaleConversion::sole();

    expect($conversion->converted_amount)->toBe('200.00')
        ->and($conversion->converted_by)->toBe($this->user->id)
        ->and($conversion->converted_currency_code)->toBe('PHP')
        ->and($conversion->sale->currency_code)->toBe('EUR');
});
