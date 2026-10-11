<?php

namespace App\Services;

use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Actions\Sales\SaveSaleConversionAction;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\Sales\SaleConversionCalculator;
use App\Services\Sales\SaleConversionValidator;
use Illuminate\Validation\ValidationException;

class SaleConversionService
{
    public function __construct(
        private SaleConversionValidator $validator,
        private SaleConversionCalculator $calculator,
    ) {}

    /**
     * Record or correct a sale's conversion into its account's currency.
     *
     * @param  array<string, mixed>  $data  raw form input; only allow-listed keys survive validation
     *
     * @throws SaleConversionException
     * @throws ValidationException
     */
    public function convert(Sale $sale, array $data, User $convertedBy): SaleConversion
    {
        $accountCurrencyCode = $this->guardAccountCurrency($sale);

        $input = $this->validator->validate($sale, $data);

        return SaveSaleConversionAction::handle(
            $sale,
            $accountCurrencyCode,
            $this->calculator->fromInput($sale, $input),
            $convertedBy,
            $input['currency_code'] ?? null,
        );
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

        $convertedCurrencyCode = $sale->saleConversion?->converted_currency_code;

        if ($convertedCurrencyCode !== null && $convertedCurrencyCode !== $accountCurrencyCode) {
            throw SaleConversionException::accountCurrencyChanged($convertedCurrencyCode, $accountCurrencyCode);
        }

        return $accountCurrencyCode;
    }
}
