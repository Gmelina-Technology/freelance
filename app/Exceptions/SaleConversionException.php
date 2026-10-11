<?php

namespace App\Exceptions;

use App\Models\Sale;
use RuntimeException;

class SaleConversionException extends RuntimeException
{
    public static function missingAccountCurrency(): self
    {
        return new self('Set your account currency in Account Profile before converting sales.');
    }

    public static function accountCurrencyChanged(string $convertedCurrencyCode, string $accountCurrencyCode): self
    {
        return new self("This sale was converted into {$convertedCurrencyCode}, but your account currency is now {$accountCurrencyCode}. Re-converting is not supported.");
    }

    public static function unknownOriginalCurrency(): self
    {
        return new self('Choose the currency this sale was paid in.');
    }

    public static function emptyBatch(): self
    {
        return new self('Select at least one sale to convert.');
    }

    public static function batchTooLarge(int $max): self
    {
        return new self("You can convert at most {$max} sales at once. Select fewer sales and try again.");
    }

    public static function batchHasForeignSales(): self
    {
        return new self("Some selected sales don't belong to this account.");
    }

    public static function batchHasConvertedSales(int $count): self
    {
        return new self("{$count} selected sale(s) are already converted. Deselect them and try again.");
    }

    public static function batchHasUnknownCurrency(int $count): self
    {
        return new self("{$count} selected sale(s) have no known currency. Convert them one at a time, or deselect them.");
    }

    public static function batchHasAccountCurrencySales(int $count, string $accountCurrencyCode): self
    {
        return new self("{$count} selected sale(s) are already in {$accountCurrencyCode}. Deselect them and try again.");
    }

    /**
     * @param  list<string>  $currencyCodes
     */
    public static function mixedCurrencies(array $currencyCodes): self
    {
        $codes = implode(', ', $currencyCodes);

        return new self("Selected sales are in different currencies ({$codes}). Select sales in one currency only.");
    }

    public static function invalidSaleInBatch(Sale $sale, string $reason): self
    {
        $name = $sale->reference_key ?? '#'.$sale->getKey();

        return new self("Sale {$name} can't be converted at this rate: {$reason}");
    }

    public static function batchSelectionChanged(): self
    {
        return new self('Some selected sales changed while converting. Refresh and try again.');
    }
}
