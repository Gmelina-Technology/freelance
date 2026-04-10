<?php

namespace App\Filament\Clusters\Settings\Resources\BankDetails\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BankDetailsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('bank_name')
                    ->label('Bank Name')
                    ->required(),
                TextInput::make('bank_address')
                    ->label('Bank Address')
                    ->required(),
                TextInput::make('bsb')
                    ->label('BSB')
                    ->required(),
                TextInput::make('swift_bic_code')
                    ->label('SWIFT/BIC Code')
                    ->required(),
                TextInput::make('account_name')
                    ->label('Account Name')
                    ->required(),
                TextInput::make('account_number')
                    ->label('Account Number')
                    ->required(),
            ]);
    }
}
