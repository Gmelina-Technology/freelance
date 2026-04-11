<?php

namespace App\Filament\Common\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class AccountProfileForm
{
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label('Account Name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Business Email')
                ->email()
                ->maxLength(255),
            Textarea::make('address')
                ->label('Business Address')
                ->maxLength(255),
        ];
    }
}
