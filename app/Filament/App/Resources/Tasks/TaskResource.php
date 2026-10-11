<?php

namespace App\Filament\App\Resources\Tasks;

use App\Filament\App\Common\GlobalSearch\GlobalSearchDetails;
use App\Filament\App\Resources\Tasks\Pages\CreateTask;
use App\Filament\App\Resources\Tasks\Pages\EditTask;
use App\Filament\App\Resources\Tasks\Pages\ListTasks;
use App\Filament\App\Resources\Tasks\Schemas\TaskForm;
use App\Filament\App\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\App\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleGroup;

    protected static ?string $navigationLabel = 'My Tasks';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return Auth::user()->isMember(Filament::getTenant())
            ? 'My Tasks'
            : 'Tasks';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'project.name'];
    }

    /**
     * @return Builder<Task>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->visibleTo(Auth::user(), Filament::getTenant())
            ->with(['project', 'assignee']);
    }

    /**
     * @param  Task  $record
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $isMember = Auth::user()->isMember(Filament::getTenant());

        return GlobalSearchDetails::filled([
            'Project' => $record->project?->name,
            'Status' => $record->status?->getLabel(),
            'Priority' => $record->priority?->getLabel(),
            'Due' => GlobalSearchDetails::date($record->due_date),
            'Assignee' => $isMember ? null : $record->assignee?->name,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            // 'view' => EditTask::route('/{record}/edit'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
