<?php

use App\Exceptions\SaleConversionException;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\SaleConversion;
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

it('converts a foreign currency sale into the account currency', function () {
    $sale = foreignSale($this->account, 1000);

    $conversion = $this->service->convert($sale, [
        'converted_amount' => '55000.50',
        'fee_amount' => '1200.25',
        'tax_amount' => '300',
        'conversion_note' => 'Wise transfer',
    ], $this->user)->fresh();

    expect($conversion->sale_id)->toBe($sale->id)
        ->and($conversion->converted_amount)->toBe('55000.50')
        ->and($conversion->gross_amount)->toBe('56500.75')
        ->and($conversion->exchange_rate)->toBe('56.5007500000')
        ->and($conversion->converted_currency_code)->toBe('PHP')
        ->and($conversion->converted_by)->toBe($this->user->id)
        ->and($sale->fresh()->currency_code)->toBe('EUR')
        ->and($sale->fresh()->isConverted())->toBeTrue();
});

it('converts a same currency sale through the same path, fees included', function () {
    $sale = Sale::factory()->for($this->account)->unconverted('PHP')->create(['amount' => 100]);

    $conversion = $this->service->convert($sale, ['converted_amount' => '97.00', 'fee_amount' => '3.00'], $this->user);

    expect($conversion->fresh()->converted_amount)->toBe('97.00')
        ->and($conversion->fresh()->gross_amount)->toBe('100.00')
        ->and($conversion->fresh()->exchange_rate)->toBe('1.0000000000');
});

it('requires the original currency when it is unknown and stores it', function () {
    $sale = foreignSale($this->account, 100, null);

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 10], $this->user))
        ->toThrow(ValidationException::class, 'Choose the currency this sale was paid in.');

    $this->service->convert($sale, ['converted_amount' => 10, 'currency_code' => 'EUR'], $this->user);

    expect($sale->fresh()->currency_code)->toBe('EUR');
});

it('refuses to convert when the account has no currency', function () {
    $sale = foreignSale(Account::factory()->create(['currency_code' => null]));

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 10], $this->user))
        ->toThrow(SaleConversionException::class, 'Set your account currency in Account Profile before converting sales.');

    expect(SaleConversion::count())->toBe(0);
});

it('refuses to edit a conversion after the account currency changed', function () {
    $sale = foreignSale($this->account);
    $this->service->convert($sale, ['converted_amount' => 100], $this->user);

    $this->account->update(['currency_code' => 'EUR']);

    expect(fn () => $this->service->convert($sale->fresh(), ['converted_amount' => 200], $this->user))
        ->toThrow(SaleConversionException::class, 'This sale was converted into PHP, but your account currency is now EUR.');

    expect(SaleConversion::sole()->converted_amount)->toBe('100.00');
});

it('writes nothing when the input is invalid', function () {
    $sale = foreignSale($this->account);

    expect(fn () => $this->service->convert($sale, ['converted_amount' => 0], $this->user))
        ->toThrow(ValidationException::class);

    expect(SaleConversion::count())->toBe(0);
});

it('writes only the allowed keys from the input', function () {
    $other = Account::factory()->withCurrency('EUR')->create();
    $sale = foreignSale($this->account, 1000);

    $this->service->convert($sale, [
        'converted_amount' => 100,
        'converted_currency_code' => 'EUR',
        'converted_by' => User::factory()->create()->id,
        'converted_at' => '2020-01-01 00:00:00',
        'exchange_rate' => 999,
        'gross_amount' => 999,
        'sale_id' => 999,
        'account_id' => $other->id,
        'amount' => 5,
    ], $this->user);

    $conversion = SaleConversion::sole();
    $sale->refresh();

    expect($conversion->sale_id)->toBe($sale->id)
        ->and($conversion->converted_currency_code)->toBe('PHP')
        ->and($conversion->converted_by)->toBe($this->user->id)
        ->and($conversion->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($conversion->exchange_rate)->toBe('0.1000000000')
        ->and($conversion->gross_amount)->toBe('100.00')
        ->and($sale->account_id)->toBe($this->account->id)
        ->and((float) $sale->amount)->toBe(1000.0);
});

it('stores a defined rate for the smallest amount and the largest net without a database error', function () {
    $sale = foreignSale($this->account, 0.01);

    DB::enableQueryLog();

    $this->service->convert($sale, ['converted_amount' => SaleConversion::MAX_AMOUNT], $this->user);

    // SQLite hands decimals back as floats, so check the values bound into the insert instead.
    $insert = collect(DB::getQueryLog())->last(fn (array $query): bool => str_starts_with($query['query'], 'insert into "sale_conversions"'));

    expect($insert['bindings'])->toContain('9999999999999.99')
        ->toContain('999999999999999.0000000000')
        ->and($sale->fresh()->isConverted())->toBeTrue();
});
