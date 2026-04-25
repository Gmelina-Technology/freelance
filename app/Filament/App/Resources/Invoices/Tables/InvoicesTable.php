<?php

namespace App\Filament\App\Resources\Invoices\Tables;

use App\Filament\App\Common\Tables\Columns\ClientInvoiceAmountColumn;
use App\Filament\App\Resources\Invoices\Actions\SentInvoiceAction;
use App\Filament\App\Resources\Invoices\Actions\VoidInvoiceAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->prefix('#')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                ClientInvoiceAmountColumn::make(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('due_date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    SentInvoiceAction::handle(),
                    VoidInvoiceAction::handle()

                ])
                    ->label('Actions'),

            ]);
    }
}
