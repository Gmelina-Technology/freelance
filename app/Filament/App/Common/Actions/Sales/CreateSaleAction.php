<?php

namespace App\Filament\App\Common\Actions\Sales;

use App\Models\Invoice;
use App\Models\Sale;

class CreateSaleAction
{

    public static function handle(array $data)
    {
        Sale::create([
            'account_id' => $data['account_id'],
            'category' => $data['category'],
            'reference_key' => $data['reference_key'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date']
        ]);
    }
}
