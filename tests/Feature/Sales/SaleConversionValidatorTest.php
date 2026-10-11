<?php

use App\Models\Currency;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Services\Sales\SaleConversionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $this->validator = app(SaleConversionValidator::class);
    $this->sale = new Sale(['amount' => 1000, 'currency_code' => 'EUR']);
});

it('returns only the allowed keys', function () {
    $validated = $this->validator->validate($this->sale, [
        'converted_amount' => 100,
        'fee_amount' => '1.50',
        'conversion_note' => 'Wise',
        'converted_currency_code' => 'EUR',
        'converted_by' => 1,
        'converted_at' => '2020-01-01 00:00:00',
        'exchange_rate' => 999,
        'gross_amount' => 999,
        'sale_id' => 5,
        'account_id' => 5,
        'amount' => 5,
    ]);

    expect($validated)->toBe([
        'converted_amount' => 100,
        'fee_amount' => '1.50',
        'conversion_note' => 'Wise',
    ]);
});

it('drops a supplied currency when the sale already has one', function () {
    $validated = $this->validator->validate($this->sale, ['converted_amount' => 10, 'currency_code' => 'EUR']);

    expect($validated)->not->toHaveKey('currency_code');
});

it('requires a known currency when the sale has none', function () {
    $sale = new Sale(['amount' => 1000, 'currency_code' => null]);

    expect(fn () => $this->validator->validate($sale, ['converted_amount' => 10]))
        ->toThrow(ValidationException::class, 'Choose the currency this sale was paid in.')
        ->and(fn () => $this->validator->validate($sale, ['converted_amount' => 10, 'currency_code' => 'XXX']))
        ->toThrow(ValidationException::class)
        ->and($this->validator->validate($sale, ['converted_amount' => 10, 'currency_code' => 'EUR']))
        ->toBe(['converted_amount' => 10, 'currency_code' => 'EUR']);
});

it('puts the combined cap error on the net amount', function () {
    try {
        $this->validator->validate($this->sale, ['converted_amount' => SaleConversion::MAX_AMOUNT, 'tax_amount' => '0.01']);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'converted_amount' => ['Net, fees and taxes together can be at most 9,999,999,999,999.99.'],
        ]);
    }
});

it('rejects invalid amounts and notes', function (array $data, string $field) {
    try {
        $this->validator->validate($this->sale, $data);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'zero net' => [['converted_amount' => 0], 'converted_amount'],
    'negative net' => [['converted_amount' => -5], 'converted_amount'],
    'missing net' => [[], 'converted_amount'],
    'three decimals' => [['converted_amount' => '10.123'], 'converted_amount'],
    'net over max' => [['converted_amount' => '10000000000000.00'], 'converted_amount'],
    'negative fee' => [['converted_amount' => 10, 'fee_amount' => -1], 'fee_amount'],
    'fee over max' => [['converted_amount' => 10, 'fee_amount' => '10000000000000.00'], 'fee_amount'],
    'negative tax' => [['converted_amount' => 10, 'tax_amount' => -1], 'tax_amount'],
    'note too long' => [['converted_amount' => 10, 'conversion_note' => str_repeat('a', 1001)], 'conversion_note'],
]);

it('requires a positive batch exchange rate with at most ten decimals', function (mixed $rate, bool $valid) {
    $call = fn () => $this->validator->validateBatch(['exchange_rate' => $rate]);

    if ($valid) {
        expect($call())->toBe(['exchange_rate' => $rate]);
    } else {
        expect($call)->toThrow(ValidationException::class);
    }
})->with([
    'missing' => [null, false],
    'zero' => [0, false],
    'negative' => ['-1', false],
    'not numeric' => ['abc', false],
    'ten decimals' => ['0.1234567890', true],
    'eleven decimals' => ['0.12345678901', false],
    'too large' => ['10000000000000', false],
]);

it('applies the same fee and tax rules to a batch', function () {
    expect(fn () => $this->validator->validateBatch(['exchange_rate' => 1, 'fee_amount' => '-1']))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->validator->validateBatch(['exchange_rate' => 1, 'tax_amount' => '1.005']))
        ->toThrow(ValidationException::class);
});

it('returns only the allowed batch keys', function () {
    $validated = $this->validator->validateBatch([
        'exchange_rate' => 2,
        'fee_amount' => '1.50',
        'conversion_note' => 'Wise',
        'converted_amount' => 100,
        'currency_code' => 'EUR',
        'account_id' => 5,
    ]);

    expect($validated)->toBe(['exchange_rate' => 2, 'fee_amount' => '1.50', 'conversion_note' => 'Wise']);
});
