<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                MultiSelect::make('assignees')
                    ->relationship('assignees', 'name')
                    ->preload(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('due_date'),
            ]);
    }
}
