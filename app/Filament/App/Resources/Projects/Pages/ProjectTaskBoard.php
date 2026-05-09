<?php

namespace App\Filament\App\Resources\Projects\Pages;

use App\Filament\App\Common\Actions\NotifyTaskAssignee;
use App\Filament\App\Common\Forms\Components\StatusField;
use App\Filament\App\Common\Schemas\TaskForm;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Filament\App\Resources\Tasks\Pages\EditTask;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardResourcePage;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Components\CardFlex;

class ProjectTaskBoard extends BoardResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.project-task-board';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Tasks';
    }

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make('addTask')
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
                ->after(fn(Task $record) => NotifyTaskAssignee::handle($record)),
            Action::make('editProject')
                ->hiddenLabel()
                ->icon(Heroicon::Cog6Tooth)
                ->outlined()
                ->url(fn() => route('filament.app.resources.projects.edit', [
                    'tenant' => Filament::getTenant(),
                    'record' => $this->getRecord(),
                ])),
        ];
    }

    public function board(Board $board): Board
    {
        return $board
            ->query(fn() => $this->getRecord()->tasks()->with(['category', 'assignee', 'project'])->orderBy('position')->getQuery())
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->searchable(['title', 'description'])
            ->filters([
                SelectFilter::make('assignee')
                    ->relationship('assignee', 'name'),
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Filter::make('overdue')->query(
                    fn($query) => $query->where('due_date', '<', now())
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
                    ->html()
                    ->hiddenLabel()
                    ->limit(100)
                    ->color('gray'),
                CardFlex::make([
                    TextEntry::make('assignee.name')
                        ->hiddenLabel()
                        ->badge()
                        ->icon('heroicon-o-user'),
                    TextEntry::make('category.name')
                        ->hiddenLabel()
                        ->badge(fn($state) => $state?->color ?? 'secondary')
                        ->icon('heroicon-o-tag'),
                ])->wrap()->justify('start'),
            ]))
            ->cardActions([
                EditAction::make('editTask')->model(Task::class)
                    ->slideOver()
                    ->schema(TaskForm::components($this->record))
                    ->after(function (Task $record) {
                        if ($record->wasChanged('assigned_user_id')) {
                            NotifyTaskAssignee::handle($record);
                        }
                    }),

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

    private function taskForm(): array
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
                        ->label('Assignee')
                        ->options(fn() => $this->getRecord()->assignees->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Select::make('category_id')
                        ->relationship('category', 'name')
                        ->manageOptionForm([
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
