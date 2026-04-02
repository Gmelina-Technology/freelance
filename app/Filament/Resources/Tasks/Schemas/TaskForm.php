<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name'),
                Select::make('project_id')
                    ->relationship('project', 'name'),
                Select::make('assigned_user_id')
                    ->relationship('assignee', 'name')
                    ->label('Assignee'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('open'),
                DateTimePicker::make('due_date'),
            ]);
    }
}
