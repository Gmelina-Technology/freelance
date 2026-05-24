<?php

namespace App\Filament\App\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Filament\App\Common\Forms\Components\StatusField;
use App\Filament\App\Resources\Tasks\Pages\EditTask;
use App\Livewire\CommentThread;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
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
                    Action::make('saveYow')
                        ->label('Save Task')
                        ->action(function (EditTask $livewire) {
                            $livewire->save(false, true);
                        }),

                    Tabs::make()->schema([
                        Tab::make('Comments')
                            ->schema([
                                Livewire::make(CommentThread::class)
                                    ->key(fn ($record): string => 'task-comments-'.$record?->getKey())
                                    ->hidden(fn ($record): bool => ! $record?->exists),
                            ]),
                        Tab::make('Work Logs')
                            ->schema([
                                RepeatableEntry::make('workLogs')
                                    ->hiddenLabel()
                                    ->table([
                                        TableColumn::make('Work Date'),
                                        TableColumn::make('Hours'),
                                        TableColumn::make('Description'),
                                    ])
                                    ->schema([
                                        TextEntry::make('worked_date')
                                            ->dateTime(),
                                        TextEntry::make('hours'),
                                        TextEntry::make('description'),
                                    ])->emptyTooltip('No work logs added yet.'),
                            ]),
                    ]),
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
                                ->hidden(fn ($record) => $record?->assigned_user_id == Auth::id())
                                ->action(function (Set $set) {
                                    $set('assigned_user_id', Auth::id());
                                }),
                        ])
                        ->label('Assignee'),
                    Select::make('priority')
                        ->options(TaskPriority::class),
                    Select::make('client_id')
                        ->relationship('client', 'name')
                        ->live(),
                    Select::make('project_id')
                        ->live()
                        ->hidden(fn (Get $get) => empty($get('client_id')))
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
