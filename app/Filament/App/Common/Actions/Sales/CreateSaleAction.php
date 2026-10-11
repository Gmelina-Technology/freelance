<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Models\Sale;

class CreateSaleAction
{
    /**
     * Record a sale in its original currency. It starts unconverted.
     *
     * @param  array{account_id: int, category: string, reference_key: ?string, amount: float|int|string, transaction_date: mixed, currency_code?: ?string}  $data
     */
    public static function handle(array $data): Sale
    {
        return Sale::create([
            'account_id' => $data['account_id'],
            'category' => $data['category'],
            'reference_key' => $data['reference_key'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'currency_code' => $data['currency_code'] ?? null,
        ]);
    }
}
