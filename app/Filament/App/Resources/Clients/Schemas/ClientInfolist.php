<?php

namespace App\Filament\App\Resources\Clients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client Details')->schema([
                    TextEntry::make('name')
                        ->inlineLabel(),
                    TextEntry::make('email')
                        ->label('Email address')
                        ->inlineLabel()
                        ->placeholder('-'),
                    TextEntry::make('phone')
                        ->inlineLabel()
                        ->placeholder('-'),
                    TextEntry::make('currency_code')
                        ->label('Currency')
                        ->inlineLabel()
                        ->placeholder('-'),
                ])->columnSpanFull(),
            ]);
    }
}
