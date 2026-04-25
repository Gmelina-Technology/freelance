<?php

namespace App\Filament\App\Common\Schemas;

use Filament\Forms\Components\FileUpload;
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
            FileUpload::make('logo')
                ->label('Account Logo')
                ->image()
                ->disk('public')
                ->directory('logos')
                ->visibility('public'),
        ];
    }
}
