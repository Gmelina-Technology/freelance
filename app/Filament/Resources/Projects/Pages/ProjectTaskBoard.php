<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardResourcePage;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Components\CardFlex;
use Relaticle\Flowforge\Concerns\InteractsWithBoard;

class ProjectTaskBoard extends BoardResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.project-task-board';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Task')
                ->icon('heroicon-o-plus')
                ->model(Task::class)
                ->schema([
                    TextInput::make('title')->required(),
                    TextInput::make('description'),
                    Select::make('status')
                        ->options([
                            'open' => 'Backlog',
                            'in_progress' => 'In Progress',
                            'review' => 'Review',
                            'completed' => 'Completed',
                        ])
                        ->default('open'),
                    Select::make('assigned_user_id')
                        ->relationship('assignee', 'name')
                        ->searchable()
                        ->preload(),
                ])
                ->mutateDataUsing(function (array $data, array $arguments): array {
                    $data['project_id'] = $this->record->id;
                    $data['client_id'] = $this->record->client_id;

                    if (empty($data['assigned_user_id'])) {
                        $data['assigned_user_id'] = Auth::id();
                    }

                    return $data;
                }),
        ];
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->record->tasks()->orderBy('position')->getQuery())
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->searchable(['title', 'description'])
            ->filters([                                       // Add filters
                SelectFilter::make('status')->options([
                    'open' => 'Backlog',
                    'in_progress' => 'In Progress',
                    'review' => 'Review',
                    'completed' => 'Completed',
                ]),
                Filter::make('overdue')->query(
                    fn($q) =>
                    $q->where('due_date', '<', now())
                ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)      // Display filters above board
            ->filtersFormWidth(Width::Large)                  // Filter panel width
            ->filtersFormColumns(3)                           // Columns in filter form
            ->columns([
                Column::make('open')->label('Backlog')->color('gray'),
                Column::make('in_progress')->label('In Progress')->color('blue'),
                Column::make('review')->label('Review')->color('amber'),
                Column::make('completed')->label('Completed')->color('green'),
            ])
            ->cardSchema(fn(Schema $schema) => $schema->components([
                TextEntry::make('description')
                    ->hiddenLabel()
                    ->limit(100)
                    ->color('gray'),
                CardFlex::make([
                    TextEntry::make('priority')
                        ->badge()
                        ->icon('heroicon-o-flag'),
                    TextEntry::make('due_date')
                        ->badge()
                        ->date()
                        ->icon('heroicon-o-calendar'),
                    TextEntry::make('assignee.name')
                        ->badge()
                        ->icon('heroicon-o-user'),
                ])->wrap()->justify('start'),
            ]))
            ->cardActions([
                EditAction::make()->model(Task::class)
                    ->schema([
                        TextInput::make('title')->required(),
                        TextInput::make('description')
                    ]),

                DeleteAction::make()->model(Task::class),
            ])
            ->cardAction('edit');
    }

    public function moveCard(
        string $cardId,
        string $targetColumnId,
        ?string $afterCardId = null,
        ?string $beforeCardId = null
    ): void {
        // Call parent to handle the actual move
        parent::moveCard($cardId, $targetColumnId, $afterCardId, $beforeCardId);

        Notification::make()
            ->title('Task moved')
            ->success()
            ->body("Task ID: {$cardId} moved to column: {$targetColumnId}")
            ->send();
    }
}
