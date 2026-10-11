<?php

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Services\Sales\SaleConversionCalculator;
use App\Services\Sales\SaleConversionFigures;

beforeEach(function () {
    $this->calculator = new SaleConversionCalculator;
});

it('adds fee and tax to the net and derives the rate from the original amount', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => 1000]), [
        'converted_amount' => '55000.50',
        'fee_amount' => '1200.25',
        'tax_amount' => 300,
        'conversion_note' => 'Wise transfer',
    ]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->convertedAmount)->toBe('55000.50')
        ->and($figures->feeAmount)->toBe('1200.25')
        ->and($figures->taxAmount)->toBe('300.00')
        ->and($figures->grossAmount)->toBe('56500.75')
        ->and($figures->exchangeRate)->toBe('56.5007500000')
        ->and($figures->conversionNote)->toBe('Wise transfer');
});

it('shows the cost of getting paid as a rate above 1 for a same currency sale with a fee', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => 100]), [
        'converted_amount' => '97.00',
        'fee_amount' => '3.00',
    ]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->grossAmount)->toBe('100.00')
        ->and($figures->exchangeRate)->toBe('1.0000000000');
});

it('keeps missing fee, tax and note as null rather than zero', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => 100]), [
        'converted_amount' => 5000,
        'fee_amount' => null,
        'tax_amount' => '',
        'conversion_note' => '',
    ]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->feeAmount)->toBeNull()
        ->and($figures->taxAmount)->toBeNull()
        ->and($figures->conversionNote)->toBeNull()
        ->and($figures->grossAmount)->toBe('5000.00');
});

it('leaves the rate null when the original amount is zero', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => 0]), ['converted_amount' => 10]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->exchangeRate)->toBeNull();
});

it('keeps cent math exact for a thirteen digit gross', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => 1000]), [
        'converted_amount' => '9999999999990.01',
        'fee_amount' => '9.98',
    ]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->convertedAmount)->toBe('9999999999990.01')
        ->and($figures->feeAmount)->toBe('9.98')
        ->and($figures->grossAmount)->toBe('9999999999999.99');
});

it('gives a defined rate for the smallest amount and the largest net', function () {
    $figures = $this->calculator->fromInput(new Sale(['amount' => '0.01']), ['converted_amount' => SaleConversion::MAX_AMOUNT]);

    expect($figures)->toBeInstanceOf(SaleConversionFigures::class)
        ->and($figures->exchangeRate)->toBe('999999999999999.0000000000');
});

it('sums net, fee and tax in cents', function () {
    expect($this->calculator->grossCents(['converted_amount' => '10.10', 'fee_amount' => '0.20', 'tax_amount' => null]))->toBe(1030)
        ->and($this->calculator->grossCents(['converted_amount' => 1]))->toBe(100);
});

it('turns a rate into the gross amount for a batch sale', function () {
    $input = $this->calculator->inputAtRate(new Sale(['amount' => 1000]), ['exchange_rate' => '0.92']);

    expect($input['converted_amount'])->toBe('920.00')
        ->and($input['fee_amount'])->toBeNull()
        ->and($input['tax_amount'])->toBeNull();
});

it('rounds the gross amount half up to the cent', function () {
    $input = $this->calculator->inputAtRate(new Sale(['amount' => 33.33]), ['exchange_rate' => '0.9']);

    expect($input['converted_amount'])->toBe('30.00');
});

it('subtracts fee and tax from the gross amount and passes the note through', function () {
    $input = $this->calculator->inputAtRate(new Sale(['amount' => 1000]), [
        'exchange_rate' => 0.92,
        'fee_amount' => '10.50',
        'tax_amount' => 5,
        'conversion_note' => 'Wise',
    ]);

    expect($input)->toBe([
        'converted_amount' => '904.50',
        'fee_amount' => '10.50',
        'tax_amount' => '5.00',
        'conversion_note' => 'Wise',
    ]);
});

it('does not reject a net at or below zero, the validator does', function () {
    $input = $this->calculator->inputAtRate(new Sale(['amount' => 10]), ['exchange_rate' => 1, 'fee_amount' => '12.00']);

    expect($input['converted_amount'])->toBe('-2.00');
});

it('rejects a batch sale whose converted amount would overflow before casting to cents', function () {
    $sale = new Sale(['amount' => 10000000, 'reference_key' => 'INV-9']);

    expect(fn () => $this->calculator->inputAtRate($sale, ['exchange_rate' => '9999999999999']))
        ->toThrow(SaleConversionException::class, "Sale INV-9 can't be converted at this rate");
});
