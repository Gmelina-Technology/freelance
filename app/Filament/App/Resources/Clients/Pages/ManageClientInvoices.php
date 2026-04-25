<?php

namespace App\Filament\App\Resources\Clients\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\App\Common\Tables\Columns\ClientInvoiceAmountColumn;
use App\Filament\App\Resources\Clients\ClientResource;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ManageClientInvoices extends ManageRelatedRecords
{
    protected static string $resource = ClientResource::class;

    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                ClientInvoiceAmountColumn::make(),
                TextColumn::make('due_date')
                    ->dateTime()
            ])
            ->groups([
                Group::make('project.name')
                    ->label('Project')
                    ->collapsible()
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class)
            ])
            ->recordActions([
                ViewAction::make()
                    ->modal()
                    ->modalHeading(fn($record) => 'Invoice: #' . $record->number)
                    ->slideOver()
                    ->modalCancelAction()
                    ->modalFooterActions([
                        ViewAction::make()
                    ])
            ]);
    }
}
