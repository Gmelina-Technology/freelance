<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Models\Sale;
use App\Services\SaleConversionService;

class CreateSaleAction
{
    /**
     * Record a sale in its original currency, converting it at 1:1 straight away when that is
     * already the account currency.
     *
     * @param  array{account_id: int, category: string, reference_key: ?string, amount: float|int|string, transaction_date: mixed, currency_code?: ?string}  $data
     */
    public static function handle(array $data): Sale
    {
        $sale = Sale::create([
            'account_id' => $data['account_id'],
            'category' => $data['category'],
            'reference_key' => $data['reference_key'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'currency_code' => $data['currency_code'] ?? null,
        ]);

        app(SaleConversionService::class)->convertAtParIfSameCurrency($sale);

        return $sale;
    }
}
