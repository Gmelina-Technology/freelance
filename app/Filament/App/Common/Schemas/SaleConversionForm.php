<?php

namespace App\Filament\App\Common\Schemas;

use App\Models\Currency;
use App\Models\Sale;
use App\Models\SaleConversion;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Number;

/**
 * Mirrors SaleConversionValidator, which stays the source of truth.
 */
class SaleConversionForm
{
    public static function components(): array
    {
        $accountCurrency = fn (): ?string => Filament::getTenant()->currency_code;

        return [
            Text::make(fn (Sale $record): string => 'Original: '.Number::format((float) $record->amount, 2).' '.($record->currency_code ?? '(currency unknown)')),
            Select::make('currency_code')
                ->label('Paid in currency')
                ->options(fn (): array => Currency::query()->orderBy('name')->pluck('name', 'code')->all())
                ->searchable()
                ->visible(fn (Sale $record): bool => $record->currency_code === null)
                ->required(fn (Sale $record): bool => $record->currency_code === null),
            TextInput::make('converted_amount')
                ->label('Net amount received')
                ->helperText('What actually reached your account, after fees and taxes.')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->maxValue((float) SaleConversion::MAX_AMOUNT)
                ->step(0.01)
                ->rule('decimal:0,2')
                ->prefix($accountCurrency),
            TextInput::make('fee_amount')
                ->label('Fees')
                ->numeric()
                ->minValue(0)
                ->maxValue((float) SaleConversion::MAX_AMOUNT)
                ->step(0.01)
                ->rule('decimal:0,2')
                ->prefix($accountCurrency),
            TextInput::make('tax_amount')
                ->label('Taxes withheld')
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
