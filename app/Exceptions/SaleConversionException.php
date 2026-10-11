<?php

namespace App\Exceptions;

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
}
