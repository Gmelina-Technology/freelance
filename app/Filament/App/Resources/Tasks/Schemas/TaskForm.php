<?php

namespace App\Filament\App\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Filament\App\Common\Forms\Components\StatusField;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
                        ->extraInputAttributes([
                            'style' => 'min-height: 20rem; max-height: 50vh; overflow-y: auto;',
                        ])
                        ->hiddenLabel(),
                ])->columnSpan(6),
                Group::make([
                    StatusField::make('status'),
                    Select::make('assigned_user_id')
                        ->options(function (Get $get) {
                            return Filament::getTenant()->users()->when($get('project_id'), function ($subQuery, $value) {
                                $subQuery->whereHas('assignedProjects', function ($projectQuery) use ($value) {
                                    $projectQuery->where('project_id', $value);
                                });
                            })->get()->pluck('name', 'id');
                        })
                        ->belowContent([
                            Action::make('assignToMe')
                                ->label('Assign to me')
                                ->action(function(Set $set) {
                                    $set('assigned_user_id', Auth::id());
                                })
                        ])
                        ->label('Assignee'),
                    Select::make('priority')
                        ->options(TaskPriority::class),
                    Select::make('client_id')
                        ->relationship('client', 'name')
                        ->live(),
                    Select::make('project_id')
                        ->live()
                        ->hidden(fn(Get $get) => empty($get('client_id')))
                        ->relationship('project', 'name', function ($query, Get $get) {
                            $query->when($get('client_id'), function ($query, $clientId) {
                                $query->where('client_id', $clientId);
                            });
                        }),
                    Select::make('category_id')
                        ->relationship('category', 'name')
                        ->createOptionForm([
                            Hidden::make('account_id')->default(Filament::getTenant()->id),
                            TextInput::make('name')
                                ->unique('categories', 'name')
                                ->required(),
                        ]),

                    DateTimePicker::make('due_date'),
                ])->columnSpan(3),
            ])->columns(9);
    }
}
