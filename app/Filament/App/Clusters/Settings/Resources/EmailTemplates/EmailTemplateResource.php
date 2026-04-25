<?php

namespace App\Filament\App\Clusters\Settings\Resources\EmailTemplates;

use App\Filament\App\Clusters\Settings\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Filament\App\Clusters\Settings\SettingsCluster;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = SettingsCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->required(),
                RichEditor::make('body')
                    ->required()
                    ->mergeTags(fn($record) => config('email-templates.merge_tags')[$record->type] ?? [])
                    ->activePanel('mergeTags'),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type'),
                TextColumn::make('subject'),
            ])
            ->recordActions([
                EditAction::make()
                    ->stickyModalHeader(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailTemplates::route('/'),
        ];
    }
}
