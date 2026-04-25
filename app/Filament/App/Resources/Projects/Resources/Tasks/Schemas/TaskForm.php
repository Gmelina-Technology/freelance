<?php

namespace App\Filament\App\Resources\Projects\Resources\Tasks\Schemas;

use App\Enums\TaskStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_id')
                    ->relationship('account', 'name')
                    ->required(),
                Select::make('client_id')
                    ->relationship('client', 'name'),
                TextInput::make('category_id')
                    ->numeric(),
                TextInput::make('assigned_user_id')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(TaskStatus::class)
                    ->default('open')
                    ->required(),
                DateTimePicker::make('due_date'),
                TextInput::make('position')
                    ->numeric(),
            ]);
    }
}
