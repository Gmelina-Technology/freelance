<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveSaleConversionAction
{
    /**
     * Create or update the sale's conversion. The original currency is only stored when the sale
     * doesn't have one yet; converted_at keeps the time of the first conversion.
     *
     * @param  array{converted_amount: string, fee_amount: ?string, tax_amount: ?string, gross_amount: string, exchange_rate: ?string, conversion_note: ?string}  $figures
     *
     * @throws SaleConversionException
     */
    public static function handle(Sale $sale, string $convertedCurrencyCode, array $figures, User $convertedBy, ?string $originalCurrencyCode = null): SaleConversion
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

            $conversion->forceFill($figures + [
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
