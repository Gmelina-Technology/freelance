<?php

namespace App\Filament\App\Resources\Invoices;

use App\Filament\App\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\App\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\App\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\App\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
