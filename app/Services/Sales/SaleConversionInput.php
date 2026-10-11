<?php

namespace App\Services\Sales;

/**
 * One sale's conversion input derived from a batch rate, before validation. Money is a decimal
 * string with 2 places. Nothing here is trusted: pass toArray() to SaleConversionValidator::validate()
 * and build figures from its result, never from this object.
 *
 * - convertedAmount: the net (gross - fee - tax); may be zero or negative, the validator rejects that
 * - feeAmount, taxAmount: null when not given
 * - conversionNote: passed through from the batch input
 */
final readonly class SaleConversionInput
{
    public function __construct(
        public string $convertedAmount,
        public ?string $feeAmount,
        public ?string $taxAmount,
        public ?string $conversionNote,
    ) {}

    /**
     * The input shape SaleConversionValidator::validate() accepts.
     *
     * @return array{converted_amount: string, fee_amount: string|null, tax_amount: string|null, conversion_note: string|null}
     */
    public function toArray(): array
    {
        return [
            'converted_amount' => $this->convertedAmount,
            'fee_amount' => $this->feeAmount,
            'tax_amount' => $this->taxAmount,
            'conversion_note' => $this->conversionNote,
        ];
    }
}
