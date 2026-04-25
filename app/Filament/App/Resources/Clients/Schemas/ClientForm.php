<?php

namespace App\Filament\App\Resources\Clients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('currency_code')
                    ->label('Currency')
                    ->relationship('currency', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
