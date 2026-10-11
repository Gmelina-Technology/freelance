<?php

use App\Exceptions\SaleConversionException;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleConversionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));

    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $this->account = Account::factory()->withCurrency('PHP')->create();
    $this->user = User::factory()->create();
    $this->service = app(SaleConversionService::class);
});

function foreignSale(Account $account, float $amount = 1000, ?string $currencyCode = 'EUR'): Sale
{
    return Sale::factory()->for($account)->unconverted($currencyCode)->create(['amount' => $amount]);
}

/**
 * The values the service binds into its UPDATE of the sale. SQLite hands decimals back as floats,
 * so 15 significant digits can't be read back exactly; MySQL returns the bound strings as stored.
 *
 * @return array<int, mixed>
 */
function capturedSaleUpdateBindings(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $update = collect(DB::getQueryLog())->last(fn (array $query): bool => str_starts_with($query['query'], 'update "sales"'));

    return $update['bindings'];
}

/**
 * The conversion columns are either all set or all null.
 */
function expectConversionInvariant(Sale $sale): void
{
    $columns = collect(['converted_at', 'converted_currency_code', 'converted_amount', 'gross_amount'])
        ->map(fn (string $column): bool => $sale->{$column} === null)
        ->unique();

    expect($columns)->toHaveCount(1);
}

it('stores net, fee and tax and computes gross and the rate', function () {
    $sale = foreignSale($this->account, 1000);

    $sale = $this->service->convert($sale, [
        'converted_amount' => '55000.50',
        'fee_amount' => '1200.25',
        'tax_amount' => '300',
        'conversion_note' => 'Wise transfer',
    ], $this->user);

    expect($sale->converted_amount)->toBe('55000.50')
        ->and($sale->fee_amount)->toBe('1200.25')
        ->and($sale->tax_amount)->toBe('300.00')
        ->and($sale->gross_amount)->toBe('56500.75')
        ->and($sale->exchange_rate)->toBe('56.5007500000')
        ->and($sale->converted_currency_code)->toBe('PHP')
        ->and($sale->currency_code)->toBe('EUR')
        ->and($sale->conversion_note)->toBe('Wise transfer')
        ->and($sale->converted_by)->toBe($this->user->id)
        ->and($sale->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($sale->isConverted())->toBeTrue();

    expectConversionInvariant($sale);
});

it('stores missing fee and tax as null rather than zero', function () {
    $sale = $this->service->convert(foreignSale($this->account, 100), ['converted_amount' => 5000]);

    expect($sale->fee_amount)->toBeNull()
        ->and($sale->tax_amount)->toBeNull()
        ->and($sale->gross_amount)->toBe('5000.00')
        ->and($sale->converted_by)->toBeNull();
});

it('leaves the rate null when the original amount is zero', function () {
    $sale = $this->service->convert(foreignSale($this->account, 0), ['converted_amount' => 10]);

    expect($sale->exchange_rate)->toBeNull()
        ->and($sale->isConverted())->toBeTrue();
});

it('stores a defined rate for the smallest amount and the largest net without a database error', function () {
    $sale = foreignSale($this->account, 0.01);

    $bindings = capturedSaleUpdateBindings(fn () => $this->service->convert($sale, ['converted_amount' => SaleConversionService::MAX_AMOUNT]));

    expect($bindings)->toContain('9999999999999.99')
        ->toContain('999999999999999.0000000000')
        ->and($sale->fresh()->isConverted())->toBeTrue();
});

it('keeps cent math exact for a thirteen digit gross', function () {
    $sale = foreignSale($this->account);

    $bindings = capturedSaleUpdateBindings(fn () => $this->service->convert($sale, [
        'converted_amount' => '9999999999990.01',
        'fee_amount' => '9.98',
    ]));

    expect($bindings)->toContain('9999999999990.01')
        ->toContain('9.98')
        ->toContain('9999999999999.99');
});

it('refuses to convert when the account has no currency', function () {
    $account = Account::factory()->create(['currency_code' => null]);
    $sale = foreignSale($account);

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 10]))
        ->toThrow(SaleConversionException::class, 'Set your account currency in Account Profile before converting sales.');

    expect($sale->fresh()->isConverted())->toBeFalse();
});

it('requires the original currency when it is unknown and stores it', function () {
    $sale = foreignSale($this->account, 100, null);

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 10]))
        ->toThrow(ValidationException::class, 'Choose the currency this sale was paid in.');

    $sale = $this->service->convert($sale, ['converted_amount' => 10, 'currency_code' => 'EUR']);

    expect($sale->currency_code)->toBe('EUR');
});

it('ignores a supplied currency when the original one is already known', function () {
    $sale = $this->service->convert(foreignSale($this->account), ['converted_amount' => 10, 'currency_code' => 'PHP']);

    expect($sale->currency_code)->toBe('EUR');
});

it('rejects an unknown original currency', function () {
    $sale = foreignSale($this->account, 100, null);

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 10, 'currency_code' => 'XXX']))
        ->toThrow(ValidationException::class);
});

it('recalculates on edit, keeps the conversion time and records the editor', function () {
    $sale = $this->service->convert(foreignSale($this->account, 1000), ['converted_amount' => 50000, 'fee_amount' => 500]);

    $this->travel(2)->days();
    $editor = User::factory()->create();

    $sale = $this->service->convert($sale, ['converted_amount' => 51000], $editor);

    expect($sale->converted_amount)->toBe('51000.00')
        ->and($sale->fee_amount)->toBeNull()
        ->and($sale->gross_amount)->toBe('51000.00')
        ->and($sale->exchange_rate)->toBe('51.0000000000')
        ->and($sale->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($sale->converted_by)->toBe($editor->id);

    expectConversionInvariant($sale);
});

it('refuses to edit a conversion after the account currency changed', function () {
    $sale = $this->service->convert(foreignSale($this->account), ['converted_amount' => 100]);

    $this->account->update(['currency_code' => 'EUR']);

    expect(fn () => $this->service->convert($sale->fresh(), ['converted_amount' => 200]))
        ->toThrow(SaleConversionException::class, 'This sale was converted into PHP, but your account currency is now EUR.');

    expect($sale->fresh()->converted_amount)->toBe('100.00');
});

it('rejects invalid amounts and notes', function (array $data) {
    $sale = foreignSale($this->account);

    expect(fn () => $this->service->convert($sale, $data))->toThrow(ValidationException::class);

    expect($sale->fresh()->isConverted())->toBeFalse();
})->with([
    'zero net' => [['converted_amount' => 0]],
    'negative net' => [['converted_amount' => -5]],
    'missing net' => [[]],
    'three decimals' => [['converted_amount' => '10.123']],
    'negative fee' => [['converted_amount' => 10, 'fee_amount' => -1]],
    'negative tax' => [['converted_amount' => 10, 'tax_amount' => -1]],
    'net over max' => [['converted_amount' => '10000000000000.00']],
    'fee over max' => [['converted_amount' => 10, 'fee_amount' => '10000000000000.00']],
    'gross over max' => [['converted_amount' => SaleConversionService::MAX_AMOUNT, 'tax_amount' => '0.01']],
    'note too long' => [['converted_amount' => 10, 'conversion_note' => str_repeat('a', 1001)]],
]);

it('writes only the allowed keys from the input', function () {
    $other = Account::factory()->withCurrency('EUR')->create();
    $sale = foreignSale($this->account, 1000);

    $sale = $this->service->convert($sale, [
        'converted_amount' => 100,
        'converted_currency_code' => 'EUR',
        'converted_by' => $this->user->id,
        'converted_at' => '2020-01-01 00:00:00',
        'exchange_rate' => 999,
        'gross_amount' => 999,
        'account_id' => $other->id,
        'amount' => 5,
    ]);

    expect($sale->converted_currency_code)->toBe('PHP')
        ->and($sale->converted_by)->toBeNull()
        ->and($sale->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($sale->exchange_rate)->toBe('0.1000000000')
        ->and($sale->gross_amount)->toBe('100.00')
        ->and($sale->account_id)->toBe($this->account->id)
        ->and((float) $sale->amount)->toBe(1000.0);
});

it('converts at par only when the sale is already in the account currency', function () {
    $same = Sale::factory()->for($this->account)->unconverted('PHP')->create(['amount' => 250]);
    $foreign = foreignSale($this->account);
    $unknown = foreignSale($this->account, 100, null);

    expect($this->service->convertAtParIfSameCurrency($same))->toBeTrue()
        ->and($this->service->convertAtParIfSameCurrency($foreign))->toBeFalse()
        ->and($this->service->convertAtParIfSameCurrency($unknown))->toBeFalse()
        ->and($this->service->convertAtParIfSameCurrency($same->fresh()))->toBeFalse();

    $same->refresh();

    expect($same->converted_amount)->toBe('250.00')
        ->and($same->gross_amount)->toBe('250.00')
        ->and($same->exchange_rate)->toBe('1.0000000000')
        ->and($same->converted_currency_code)->toBe('PHP')
        ->and($foreign->fresh()->isConverted())->toBeFalse();

    expectConversionInvariant($same);
    expectConversionInvariant($foreign->fresh());
});
