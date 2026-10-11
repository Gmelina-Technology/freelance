<?php

use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Actions\Sales\SaveSaleConversionAction;
use App\Models\Account;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));

    $this->account = Account::factory()->withCurrency('PHP')->create();
    $this->user = User::factory()->create();
});

/**
 * @return array{converted_amount: string, fee_amount: ?string, tax_amount: ?string, gross_amount: string, exchange_rate: ?string, conversion_note: ?string}
 */
function conversionFigures(string $net, ?string $fee = null): array
{
    return [
        'converted_amount' => $net,
        'fee_amount' => $fee,
        'tax_amount' => null,
        'gross_amount' => number_format((float) $net + (float) $fee, 2, '.', ''),
        'exchange_rate' => '1.0000000000',
        'conversion_note' => null,
    ];
}

it('creates the conversion and sets it on the given sale', function () {
    $sale = Sale::factory()->for($this->account)->unconverted('EUR')->create();

    $conversion = SaveSaleConversionAction::handle($sale, 'PHP', conversionFigures('500.00', '5.00'), $this->user);

    expect($conversion->sale_id)->toBe($sale->id)
        ->and($conversion->converted_currency_code)->toBe('PHP')
        ->and($conversion->converted_amount)->toBe('500.00')
        ->and($conversion->fee_amount)->toBe('5.00')
        ->and($conversion->gross_amount)->toBe('505.00')
        ->and($conversion->converted_by)->toBe($this->user->id)
        ->and($conversion->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($sale->saleConversion)->toBe($conversion)
        ->and($sale->isConverted())->toBeTrue();
});

it('updates the same row on edit, keeps the conversion time and records the editor', function () {
    $sale = Sale::factory()->for($this->account)->unconverted('EUR')->create();
    SaveSaleConversionAction::handle($sale, 'PHP', conversionFigures('500.00', '5.00'), $this->user);

    $this->travel(2)->days();
    $editor = User::factory()->create();

    $conversion = SaveSaleConversionAction::handle($sale, 'PHP', conversionFigures('510.00'), $editor);

    expect(SaleConversion::count())->toBe(1)
        ->and($conversion->fresh()->converted_amount)->toBe('510.00')
        ->and($conversion->fresh()->fee_amount)->toBeNull()
        ->and($conversion->fresh()->converted_at->toDateTimeString())->toBe('2026-10-11 09:00:00')
        ->and($conversion->fresh()->converted_by)->toBe($editor->id);
});

it('stores the original currency only when the sale has none', function () {
    $unknown = Sale::factory()->for($this->account)->unconverted()->create();
    $known = Sale::factory()->for($this->account)->unconverted('EUR')->create();

    SaveSaleConversionAction::handle($unknown, 'PHP', conversionFigures('10.00'), $this->user, 'USD');
    SaveSaleConversionAction::handle($known, 'PHP', conversionFigures('10.00'), $this->user, 'USD');

    expect($unknown->currency_code)->toBe('USD')
        ->and($unknown->fresh()->currency_code)->toBe('USD')
        ->and($known->fresh()->currency_code)->toBe('EUR');
});

it('refuses to update a conversion made into another currency', function () {
    $sale = Sale::factory()->for($this->account)->converted('EUR', 100)->create();

    expect(fn () => SaveSaleConversionAction::handle($sale, 'PHP', conversionFigures('200.00'), $this->user))
        ->toThrow(SaleConversionException::class, 'This sale was converted into EUR, but your account currency is now PHP.');

    expect($sale->saleConversion()->sole()->converted_amount)->toBe('100.00');
});

it('only lets user-entered figures be mass assigned', function () {
    $conversion = new SaleConversion([
        'sale_id' => 1,
        'converted_amount' => '10.00',
        'fee_amount' => '1.00',
        'tax_amount' => '2.00',
        'conversion_note' => 'Note',
        'converted_currency_code' => 'EUR',
        'gross_amount' => '13.00',
        'exchange_rate' => 1,
        'converted_at' => now(),
        'converted_by' => 1,
    ]);

    expect(array_keys($conversion->getAttributes()))->toBe(['converted_amount', 'fee_amount', 'tax_amount', 'conversion_note']);
});
