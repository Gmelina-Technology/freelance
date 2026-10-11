<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\Sales\SaleConversionBatchItem;
use App\Services\Sales\SaleConversionFigures;
use Illuminate\Support\Collection;
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

    /**
     * Save every conversion in one transaction: lock all sales, re-check that none is converted
     * yet, then save each through handle(). Any exception rolls back the whole batch.
     *
     * @param  list<SaleConversionBatchItem>  $items
     * @return Collection<int, SaleConversion>
     *
     * @throws SaleConversionException
     */
    public static function handleMany(array $items, string $convertedCurrencyCode, User $convertedBy): Collection
    {
        $ids = array_map(fn (SaleConversionBatchItem $item) => $item->sale->getKey(), $items);

        return DB::transaction(function () use ($items, $ids, $convertedCurrencyCode, $convertedBy) {
            $locked = Sale::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

            if ($locked->count() < count($ids)) {
                throw SaleConversionException::batchSelectionChanged();
            }

            $convertedCount = Sale::query()->whereKey($ids)->has('saleConversion')->count();

            if ($convertedCount > 0) {
                throw SaleConversionException::batchHasConvertedSales($convertedCount);
            }

            return collect($items)->map(
                fn (SaleConversionBatchItem $item) => self::handle($item->sale, $convertedCurrencyCode, $item->figures, $convertedBy)
            );
        });
    }
}
