<?php

namespace App\Filament\App\Common\Schemas;

use App\Models\SaleConversion;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Mirrors SaleConversionValidator::validateBatch(), which stays the source of truth.
 */
class SaleBatchConversionForm
{
    public static function components(): array
    {
        $accountCurrency = fn (): ?string => Filament::getTenant()->currency_code;

        return [
            TextInput::make('exchange_rate')
                ->label('Exchange rate')
                ->helperText(fn (): string => "How much 1 unit of the sales' currency is worth in your account currency ({$accountCurrency()}).")
                ->numeric()
                ->required()
                ->rule('gt:0')
                ->rule('decimal:0,10')
                ->maxValue((float) SaleConversion::MAX_AMOUNT)
                ->step(0.0000000001),
            TextInput::make('fee_amount')
                ->label('Fees per sale')
                ->helperText('Charged on each selected sale.')
                ->numeric()
                ->minValue(0)
                ->maxValue((float) SaleConversion::MAX_AMOUNT)
                ->step(0.01)
                ->rule('decimal:0,2')
                ->prefix($accountCurrency),
            TextInput::make('tax_amount')
                ->label('Taxes per sale')
                ->helperText('Charged on each selected sale.')
                ->numeric()
                ->minValue(0)
                ->maxValue((float) SaleConversion::MAX_AMOUNT)
                ->step(0.01)
                ->rule('decimal:0,2')
                ->prefix($accountCurrency),
            Textarea::make('conversion_note')
                ->label('Note')
                ->maxLength(1000)
                ->rows(2),
        ];
    }
}
