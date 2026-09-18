<?php

namespace App\Filament\App\Resources\Quotes;

use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Pages\CreateQuote;
use App\Filament\App\Resources\Quotes\Pages\EditQuote;
use App\Filament\App\Resources\Quotes\Pages\ListQuotes;
use App\Filament\App\Resources\Quotes\Pages\ViewQuote;
use App\Filament\App\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\App\Resources\Quotes\Schemas\QuoteInfolist;
use App\Filament\App\Resources\Quotes\Tables\QuotesTable;
use App\Models\Quote;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Quotes';

    public static function canAccess(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === QuoteStatus::Draft;
    }

    public static function form(Schema $schema): Schema
    {
        return QuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotesTable::configure($table);
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
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'view' => ViewQuote::route('/{record}'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }
}
