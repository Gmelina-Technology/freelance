<?php

namespace App\Services;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleConversionService
{
    public const MAX_AMOUNT = '9999999999999.99';

    /**
     * Record or correct a sale's conversion into its account's currency. Only the keys below are
     * read from $data; everything else on the sale is computed here.
     *
     * @param  array{converted_amount: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string, currency_code?: ?string}  $data
     *
     * @throws SaleConversionException
     * @throws ValidationException
     */
    public function convert(Sale $sale, array $data, ?User $convertedBy = null): Sale
    {
        $accountCurrencyCode = $this->guardAccountCurrency($sale);

        $validated = Validator::make($data, [
            'converted_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.self::MAX_AMOUNT],
            'fee_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
            'tax_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
            'conversion_note' => ['nullable', 'string', 'max:1000'],
            'currency_code' => [Rule::requiredIf($sale->currency_code === null), 'nullable', 'string', 'exists:currencies,code'],
        ], [
            'currency_code.required' => SaleConversionException::unknownOriginalCurrency()->getMessage(),
        ])->validate();

        $netCents = $this->toCents($validated['converted_amount']);
        $feeCents = filled($validated['fee_amount'] ?? null) ? $this->toCents($validated['fee_amount']) : null;
        $taxCents = filled($validated['tax_amount'] ?? null) ? $this->toCents($validated['tax_amount']) : null;
        $grossCents = $netCents + ($feeCents ?? 0) + ($taxCents ?? 0);

        if ($grossCents > $this->toCents(self::MAX_AMOUNT)) {
            throw ValidationException::withMessages([
                'converted_amount' => 'Net, fees and taxes together can be at most '.number_format((float) self::MAX_AMOUNT, 2).'.',
            ]);
        }

        DB::transaction(function () use ($sale, $validated, $convertedBy, $accountCurrencyCode, $netCents, $feeCents, $taxCents, $grossCents) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            $this->guardAccountCurrency($locked);

            $amountCents = $this->toCents($locked->amount);

            $attributes = [
                'converted_currency_code' => $accountCurrencyCode,
                'converted_amount' => $this->fromCents($netCents),
                'fee_amount' => $feeCents === null ? null : $this->fromCents($feeCents),
                'tax_amount' => $taxCents === null ? null : $this->fromCents($taxCents),
                'gross_amount' => $this->fromCents($grossCents),
                'exchange_rate' => $amountCents > 0 ? number_format($grossCents / $amountCents, 10, '.', '') : null,
                'conversion_note' => filled($validated['conversion_note'] ?? null) ? $validated['conversion_note'] : null,
                'converted_by' => $convertedBy?->getKey(),
            ];

            if ($locked->currency_code === null) {
                $attributes['currency_code'] = $validated['currency_code'];
            }

            if (! $locked->isConverted()) {
                $attributes['converted_at'] = now();
            }

            $locked->forceFill($attributes)->save();
        });

        return $sale->refresh();
    }

    /**
     * Convert at 1:1 when the sale is already in its account's currency. No-op otherwise.
     */
    public function convertAtParIfSameCurrency(Sale $sale): bool
    {
        $accountCurrencyCode = $sale->account?->currency_code;

        if ($sale->isConverted() || $sale->currency_code === null || $accountCurrencyCode === null || $sale->currency_code !== $accountCurrencyCode) {
            return false;
        }

        $sale->forceFill([
            'converted_currency_code' => $sale->currency_code,
            'converted_amount' => $sale->amount,
            'fee_amount' => null,
            'tax_amount' => null,
            'gross_amount' => $sale->amount,
            'exchange_rate' => 1,
            'conversion_note' => null,
            'converted_at' => now(),
            'converted_by' => null,
        ])->save();

        return true;
    }

    /**
     * @throws SaleConversionException
     */
    private function guardAccountCurrency(Sale $sale): string
    {
        $accountCurrencyCode = $sale->account?->currency_code;

        if ($accountCurrencyCode === null) {
            throw SaleConversionException::missingAccountCurrency();
        }

        if ($sale->isConverted() && $sale->converted_currency_code !== $accountCurrencyCode) {
            throw SaleConversionException::accountCurrencyChanged($sale->converted_currency_code, $accountCurrencyCode);
        }

        return $accountCurrencyCode;
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
