<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\Actions\SentInvoiceAction;
use App\Filament\App\Resources\Invoices\Actions\VoidInvoiceAction;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SentInvoiceAction::handle(),
            VoidInvoiceAction::handle(),
            EditAction::make()
                ->visible(fn ($record): bool => InvoiceResource::canEdit($record)),
            DeleteAction::make()
                ->visible(fn ($record): bool => InvoiceResource::canDelete($record)),
        ];
    }
}
