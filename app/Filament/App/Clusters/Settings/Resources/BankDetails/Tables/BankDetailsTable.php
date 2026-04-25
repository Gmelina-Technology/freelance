<?php

namespace App\Filament\App\Clusters\Settings\Resources\BankDetails\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankDetailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')
                    ->label('Bank Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable(),
                TextColumn::make('bsb')
                    ->label('BSB')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
