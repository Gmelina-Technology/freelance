<?php

namespace App\Services\Sales;

/**
 * The figures stored on a SaleConversion, as computed by SaleConversionCalculator. Money is a
 * decimal string with 2 places and the rate a decimal string with 10, never a float.
 *
 * - convertedAmount: the net received
 * - feeAmount, taxAmount: null when not applicable
 * - grossAmount: net + fee + tax
 * - exchangeRate: gross / sale amount, null when the sale amount is 0
 * - conversionNote: null when blank
 *
 * Persistence maps each property to its column explicitly; this class knows no column names.
 */
final readonly class SaleConversionFigures
{
    public function __construct(
        public string $convertedAmount,
        public ?string $feeAmount,
        public ?string $taxAmount,
        public string $grossAmount,
        public ?string $exchangeRate,
        public ?string $conversionNote,
    ) {}
}
