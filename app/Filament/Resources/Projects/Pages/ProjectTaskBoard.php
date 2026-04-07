<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
                ->schema(self::taskForm())
                ->mutateDataUsing(function (array $data, array $arguments): array {
                    $data['project_id'] = $this->record->id;
                    $data['client_id'] = $this->record->client_id;

                    if (empty($data['assigned_user_id'])) {
                        $data['assigned_user_id'] = Auth::id();
                    }

                    return $data;
                })
                ->after(fn(Task $record) => Notification::make('notifyAssignee')
                    ->title('Task Created')
                    ->body("Task '{$record->title}' has been created and assigned to you.")
                    ->success()
                    ->sendToDatabase($record->assignee)),
            Action::make('editProject')
                ->hiddenLabel()
                ->icon(Heroicon::Cog6Tooth)
                ->outlined()
                ->url(fn() => route('filament.app.resources.projects.edit', [
                    'tenant' => Filament::getTenant(),
                    'record' => $this->record
                ]))
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
                    TextEntry::make('assignee.name')
                        ->hiddenLabel()
                        ->badge()
                        ->icon('heroicon-o-user'),
                ])->wrap()->justify('start'),
            ]))
            ->cardActions([
                EditAction::make()->model(Task::class)
                    ->slideOver()
                    ->schema(self::taskForm()),

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

    public static function taskForm(): array
    {
        return [
            Group::make([
                Group::make([
                    TextInput::make('title')->required(),
                    RichEditor::make('description')

                ])->columnSpan(5),
                Group::make([
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
                ])->columnSpan(2)
            ])->columns(7),
        ];
    }
}
