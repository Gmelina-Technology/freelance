<?php

namespace App\Services\Sales;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;

/**
 * Turns validated conversion input into the figures stored on a SaleConversion. All money math is
 * done in integer cents so large values stay exact.
 */
class SaleConversionCalculator
{
    /**
     * @param  array{converted_amount: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string}  $input
     */
    public function fromInput(Sale $sale, array $input): SaleConversionFigures
    {
        $feeCents = $this->optionalCents($input['fee_amount'] ?? null);
        $taxCents = $this->optionalCents($input['tax_amount'] ?? null);
        $grossCents = $this->grossCents($input);
        $amountCents = $this->toCents($sale->amount);

        return new SaleConversionFigures(
            convertedAmount: $this->fromCents($this->toCents($input['converted_amount'])),
            feeAmount: $feeCents === null ? null : $this->fromCents($feeCents),
            taxAmount: $taxCents === null ? null : $this->fromCents($taxCents),
            grossAmount: $this->fromCents($grossCents),
            exchangeRate: $amountCents > 0 ? number_format($grossCents / $amountCents, 10, '.', '') : null,
            conversionNote: filled($input['conversion_note'] ?? null) ? $input['conversion_note'] : null,
        );
    }

    /**
     * Net + fee + tax, in cents.
     *
     * @param  array{converted_amount: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null}  $input
     */
    public function grossCents(array $input): int
    {
        return $this->toCents($input['converted_amount'])
            + ($this->optionalCents($input['fee_amount'] ?? null) ?? 0)
            + ($this->optionalCents($input['tax_amount'] ?? null) ?? 0);
    }

    /**
     * Per-sale conversion input for a batch: gross = sale amount x rate (rounded half-up to cents),
     * net = gross - fee - tax. Fee and tax are applied as-is to each sale. The net may come out at
     * or below zero; the validator rejects that.
     *
     * @param  array{exchange_rate: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string}  $batchInput
     *
     * @throws SaleConversionException
     */
    public function inputAtRate(Sale $sale, array $batchInput): SaleConversionInput
    {
        $rate = (float) $batchInput['exchange_rate'];

        // Compared as floats, before the int cast below could wrap.
        if ((float) $sale->amount * $rate > (float) SaleConversion::MAX_AMOUNT) {
            throw SaleConversionException::invalidSaleInBatch(
                $sale,
                'the converted amount would be above '.number_format((float) SaleConversion::MAX_AMOUNT, 2).'.',
            );
        }

        $feeCents = $this->optionalCents($batchInput['fee_amount'] ?? null);
        $taxCents = $this->optionalCents($batchInput['tax_amount'] ?? null);
        $grossCents = (int) round($this->toCents($sale->amount) * $rate);

        return new SaleConversionInput(
            convertedAmount: $this->fromCents($grossCents - ($feeCents ?? 0) - ($taxCents ?? 0)),
            feeAmount: $feeCents === null ? null : $this->fromCents($feeCents),
            taxAmount: $taxCents === null ? null : $this->fromCents($taxCents),
            conversionNote: $batchInput['conversion_note'] ?? null,
        );
    }

    private function optionalCents(float|int|string|null $value): ?int
    {
        return filled($value) ? $this->toCents($value) : null;
    }

    private function toCents(float|int|string $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function fromCents(int $cents): string
    {
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }
}
