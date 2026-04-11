<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                Select::make('assignees')
                    ->relationship('assignees', 'name', fn(Builder $query) =>
                    $query->whereHas('accounts', fn($subQuery) => $subQuery->whereKey(Filament::getTenant()->id)))
                    ->multiple()
                    ->preload(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('due_date'),
            ]);
    }
}
