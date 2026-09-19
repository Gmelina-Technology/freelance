<?php

namespace App\Filament\App\Clusters\Settings\Resources\EmailTemplates;

use App\Filament\App\Clusters\Settings\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Filament\App\Clusters\Settings\SettingsCluster;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                    ->mergeTags(fn ($record) => config('email-templates.merge_tags')[$record->type->name] ?? [])
                    ->activePanel('mergeTags'),
                Section::make('Features')
                    ->description('Optional behaviour for emails sent from this template.')
                    ->schema(self::featureToggles())
                    ->visible(fn (?EmailTemplate $record): bool => $record !== null && $record->availableFeatures() !== []),
            ])->columns(1);
    }

    /**
     * One toggle per feature flag declared in config/email-templates.php, shown only for the template types that support it.
     *
     * @return array<int, Toggle>
     */
    private static function featureToggles(): array
    {
        $featureKeys = collect(config('email-templates.features', []))
            ->flatMap(fn (array $features): array => array_keys($features))
            ->unique();

        return $featureKeys
            ->map(fn (string $key): Toggle => Toggle::make("metadata.features.{$key}")
                ->label(fn (?EmailTemplate $record): string => $record?->availableFeatures()[$key]['label'] ?? $key)
                ->helperText(fn (?EmailTemplate $record): ?string => $record?->availableFeatures()[$key]['description'] ?? null)
                ->default(false)
                ->visible(fn (?EmailTemplate $record): bool => array_key_exists($key, $record?->availableFeatures() ?? [])))
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type.name'),
                TextColumn::make('subject'),
            ])
            ->recordActions([
                EditAction::make()
                    ->stickyModalHeader()
                    ->mutateDataUsing(function (array $data, EmailTemplate $record): array {
                        // The form only knows about feature flags, so keep any other metadata already stored.
                        if (isset($data['metadata'])) {
                            $data['metadata'] = array_replace_recursive($record->metadata ?? [], $data['metadata']);
                        }

                        return $data;
                    }),
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
