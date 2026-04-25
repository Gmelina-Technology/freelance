<?php

namespace App\Filament\App\Common\Schemas;

use App\Filament\App\Common\Forms\Components\StatusField;
use App\Models\Project;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;

class TaskForm
{
    public static function components(Project $record): array
    {
        return [
            Group::make([
                Group::make([
                    TextInput::make('title')->required(),
                    RichEditor::make('description'),
                ])->columnSpan(5),
                Group::make([
                    StatusField::make('status'),
                    Select::make('assigned_user_id')
                        ->options(fn () => $record->assignees->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Select::make('category_id')
                        ->relationship('category', 'name')

                        ->createOptionForm([
                            Hidden::make('account_id')->default($record->account_id),
                            TextInput::make('name')->required(),
                            TextInput::make('color')->type('color'),
                        ])
                        ->searchable()
                        ->preload(),
                ])->columnSpan(2),
            ])->columns(7),
        ];
    }
}
