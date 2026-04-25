<?php

namespace App\Filament\App\Resources\Clients;

use App\Filament\App\Resources\Clients\Pages\CreateClient;
use App\Filament\App\Resources\Clients\Pages\EditClient;
use App\Filament\App\Resources\Clients\Pages\ListClients;
use App\Filament\App\Resources\Clients\Pages\ManageClientInvoices;
use App\Filament\App\Resources\Clients\Pages\ViewClient;
use App\Filament\App\Resources\Clients\Schemas\ClientForm;
use App\Filament\App\Resources\Clients\Schemas\ClientInfolist;
use App\Filament\App\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditClient::class,
            ManageClientInvoices::class
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
            'invoices' => ManageClientInvoices::route('/{record}/invoices'),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()->isOwner(Filament::getTenant());
    }
}
