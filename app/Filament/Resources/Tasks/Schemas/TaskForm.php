<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Filament\Common\Forms\Components\StatusField;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    TextInput::make('title')
                        ->required(),
                    RichEditor::make('description')
                        ->hiddenLabel(),
                ])->columnSpan(6),
                Group::make([
                    Select::make('client_id')
                        ->relationship('client', 'name')
                        ->live(),
                    Select::make('project_id')
                        ->relationship('project', 'name', function ($query, Get $get) {
                            $query->when($get('client_id'), function ($query, $clientId) {
                                $query->where('client_id', $clientId);
                            });
                        }),
                    Select::make('assigned_user_id')
                        ->relationship('assignee', 'name')
                        ->label('Assignee'),
                    StatusField::make('status'),
                    DateTimePicker::make('due_date'),
                ])->columnSpan(3),
            ])->columns(9);
    }
}
