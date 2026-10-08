<?php

namespace App\Filament\App\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\App\Common\Tables\Columns\ClientInvoiceAmountColumn;
use App\Filament\App\Resources\Invoices\Actions\MarkAsPaidAction;
use App\Filament\App\Resources\Invoices\Actions\SentInvoiceAction;
use App\Filament\App\Resources\Invoices\Actions\VoidInvoiceAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByStatusThenDueDate($direction)),
                TextColumn::make('due_date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByStatusThenDueDate())
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    MarkAsPaidAction::handle(),
                    SentInvoiceAction::handle(),
                    VoidInvoiceAction::handle(),
                ])
                    ->label('Actions'),

            ]);
    }
}
