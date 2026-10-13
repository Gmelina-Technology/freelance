<?php

namespace App\Filament\App\Resources\Sales;

use App\Enums\AccountCapability;
use App\Filament\App\Resources\Sales\Actions\ConvertSaleAction;
use App\Filament\App\Resources\Sales\Actions\ConvertSalesBulkAction;
use App\Filament\App\Resources\Sales\Actions\EditSaleConversionAction;
use App\Filament\App\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return Auth::user()->can(AccountCapability::ManageSales, Filament::getTenant());
    }

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
                    ->label('Original amount')
                    ->numeric(decimalPlaces: 2)
                    ->description(fn (Sale $record): string => $record->currency_code ?? 'Currency unknown')
                    ->sortable(),
                TextColumn::make('conversion_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('saleConversion.converted_amount')
                    ->label('Net received')
                    ->money(fn (Sale $record): ?string => $record->saleConversion?->converted_currency_code)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('saleConversion.fee_amount')
                    ->label('Fees')
                    ->money(fn (Sale $record): ?string => $record->saleConversion?->converted_currency_code)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saleConversion.tax_amount')
                    ->label('Taxes')
                    ->money(fn (Sale $record): ?string => $record->saleConversion?->converted_currency_code)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saleConversion.gross_amount')
                    ->label('Gross')
                    ->money(fn (Sale $record): ?string => $record->saleConversion?->converted_currency_code)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saleConversion.exchange_rate')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 6)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saleConversion.converted_at')
                    ->label('Converted at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_key')
                    ->url(
                        fn (Sale $record) => $record->invoice ? route('filament.app.resources.invoices.view', [
                            'tenant' => Filament::getTenant(),
                            'record' => $record->invoice,
                        ]) : null
                    )
                    ->searchable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['invoice', 'saleConversion']))
            ->filters([
                TernaryFilter::make('converted')
                    ->label('Conversion')
                    ->placeholder('All sales')
                    ->trueLabel('Converted only')
                    ->falseLabel('Unconverted only')
                    ->queries(
                        true: fn (Builder $query) => $query->converted(),
                        false: fn (Builder $query) => $query->unconverted(),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                ConvertSaleAction::handle(),
                EditSaleConversionAction::handle(),
            ])
            ->toolbarActions([
                ConvertSalesBulkAction::handle(),
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
            'index' => ListSales::route('/'),
        ];
    }
}
