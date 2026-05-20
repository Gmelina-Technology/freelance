<?php

namespace App\Filament\App\Resources\Clients\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
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
                Repeater::make('pocs')
                    ->label('Point of Contacts')
                    ->reorderable(false)
                    ->table([
                        TableColumn::make('Name'),
                        TableColumn::make('Email address'),
                        TableColumn::make('Phone number'),
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->required()
                            ->email(),
                        TextInput::make('phone')
                            ->tel(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Add Point of Contact')
                    ->columnSpan(2),
            ]);
    }
}
