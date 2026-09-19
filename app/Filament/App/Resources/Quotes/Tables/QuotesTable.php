<?php

namespace App\Filament\App\Resources\Quotes\Tables;

use App\Enums\QuoteStatus;
use App\Filament\App\Common\Tables\Columns\ClientInvoiceAmountColumn;
use App\Filament\App\Resources\Quotes\Actions\AcceptQuoteAction;
use App\Filament\App\Resources\Quotes\Actions\DeclineQuoteAction;
use App\Filament\App\Resources\Quotes\Actions\SendQuoteAction;
use App\Filament\App\Resources\Quotes\Actions\VoidQuoteAction;
use Filament\Actions\ActionGroup;
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
                ClientInvoiceAmountColumn::make()
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
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->filters([
                SelectFilter::make('status')
                    ->options(QuoteStatus::class),
                SelectFilter::make('client_id')
                    ->relationship('client', 'name'),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    SendQuoteAction::handle(),
                    AcceptQuoteAction::handle(),
                    DeclineQuoteAction::handle(),
                    VoidQuoteAction::handle(),
                ])
                    ->label('Actions'),
            ]);
    }
}
