<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\Actions\SentInvoiceAction;
use App\Filament\App\Resources\Invoices\Actions\VoidInvoiceAction;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SentInvoiceAction::handle(),
            VoidInvoiceAction::handle(),
        ];
    }
}
