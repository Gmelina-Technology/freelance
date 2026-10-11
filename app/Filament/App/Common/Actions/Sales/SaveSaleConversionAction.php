<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\Sales\SaleConversionFigures;
use Illuminate\Support\Facades\DB;

class SaveSaleConversionAction
{
    /**
     * Create or update the sale's conversion. The original currency is only stored when the sale
     * doesn't have one yet; converted_at keeps the time of the first conversion. Each column is
     * named here, so a figure only reaches the row if it is mapped explicitly.
     *
     * @throws SaleConversionException
     */
    public static function handle(Sale $sale, string $convertedCurrencyCode, SaleConversionFigures $figures, User $convertedBy, ?string $originalCurrencyCode = null): SaleConversion
    {
        [$conversion, $currencyCode] = DB::transaction(function () use ($sale, $convertedCurrencyCode, $figures, $convertedBy, $originalCurrencyCode) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());
            $conversion = $locked->saleConversion()->first();

            if ($conversion !== null && $conversion->converted_currency_code !== $convertedCurrencyCode) {
                throw SaleConversionException::accountCurrencyChanged($conversion->converted_currency_code, $convertedCurrencyCode);
            }

            if ($locked->currency_code === null && $originalCurrencyCode !== null) {
                $locked->forceFill(['currency_code' => $originalCurrencyCode])->save();
            }

            $conversion ??= new SaleConversion;

            $conversion->forceFill([
                'converted_amount' => $figures->convertedAmount,
                'fee_amount' => $figures->feeAmount,
                'tax_amount' => $figures->taxAmount,
                'gross_amount' => $figures->grossAmount,
                'exchange_rate' => $figures->exchangeRate,
                'conversion_note' => $figures->conversionNote,
                'converted_currency_code' => $convertedCurrencyCode,
                'converted_by' => $convertedBy->getKey(),
            ]);

            if (! $conversion->exists) {
                $conversion->forceFill(['converted_at' => now()]);
            }

            $locked->saleConversion()->save($conversion);

            return [$conversion, $locked->currency_code];
        });

        $sale->setAttribute('currency_code', $currencyCode);
        $sale->syncOriginalAttribute('currency_code');
        $sale->setRelation('saleConversion', $conversion);

        return $conversion;
    }
}
