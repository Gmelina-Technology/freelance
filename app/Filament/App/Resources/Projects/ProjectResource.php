<?php

namespace App\Filament\App\Resources\Projects;

use App\Filament\App\Common\GlobalSearch\GlobalSearchDetails;
use App\Filament\App\Resources\Projects\Pages\CreateProject;
use App\Filament\App\Resources\Projects\Pages\EditProject;
use App\Filament\App\Resources\Projects\Pages\ListProjects;
use App\Filament\App\Resources\Projects\Pages\ProjectTaskBoard;
use App\Filament\App\Resources\Projects\Schemas\ProjectForm;
use App\Filament\App\Resources\Projects\Schemas\ProjectInfolist;
use App\Filament\App\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'client.name'];
    }

    /**
     * @return Builder<Project>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->visibleTo(Auth::user(), Filament::getTenant())
            ->with(['client']);
    }

    /**
     * @param  Project  $record
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return GlobalSearchDetails::filled([
            'Client' => $record->client?->name,
            'Status' => $record->status?->getLabel(),
            'Due' => GlobalSearchDetails::date($record->due_date),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
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
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ProjectTaskBoard::route('/{record}/tasks'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
