<?php

namespace App\Filament\App\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    TextInput::make('name')
                        ->required(),
                    Select::make('assignees')
                        ->relationship('assignees', 'name', fn(Builder $query) => $query->whereHas('accounts', fn($subQuery) => $subQuery->whereKey(Filament::getTenant()->id)))
                        ->multiple()
                        ->preload(),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ])->columnSpan(5),
                Group::make([
                    Select::make('status')
                        ->options(ProjectStatus::class)
                        ->required()
                        ->default(ProjectStatus::BACKLOG),
                    Select::make('client_id')
                        ->relationship('client', 'name')
                        ->required(),
                    DateTimePicker::make('due_date'),
                ])->columnSpan(2),
            ])->columns(7);
    }
}
