<?php

namespace App\Filament\App\Resources\Projects\Resources\Tasks;

use App\Filament\App\Resources\Projects\ProjectResource;
use App\Filament\App\Resources\Projects\Resources\Tasks\Pages\CreateTask;
use App\Filament\App\Resources\Projects\Resources\Tasks\Pages\EditTask;
use App\Filament\App\Resources\Projects\Resources\Tasks\Schemas\TaskForm;
use App\Filament\App\Resources\Projects\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ProjectResource::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
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
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
