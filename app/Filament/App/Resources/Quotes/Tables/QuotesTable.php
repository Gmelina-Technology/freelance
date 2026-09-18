<?php

namespace App\Filament\App\Resources\Quotes\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->prefix('#')
                    ->searchable(),
                TextColumn::make('invoice_ref')
                    ->label('Invoice')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.name')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->client->currency_code)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('issued_at')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->relationship('client', 'name'),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name'),
            ]);
    }
}
