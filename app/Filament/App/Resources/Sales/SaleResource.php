<?php

namespace App\Filament\App\Resources\Sales;

use App\Filament\App\Resources\Sales\Pages\CreateSale;
use App\Filament\App\Resources\Sales\Pages\EditSale;
use App\Filament\App\Resources\Sales\Pages\ListSales;
use App\Filament\App\Resources\Sales\Schemas\SaleForm;
use App\Filament\App\Resources\Sales\Tables\SalesTable;
use App\Models\Invoice;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_key')
                    ->url(
                        fn(Sale $record) => route('filament.app.resources.invoices.view', [
                            'tenant' => Filament::getTenant(),
                            'record' => $record->invoice
                        ])
                    )
                    ->searchable(),
            ]);
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
            'index' => ListSales::route('/')
        ];
    }
}
