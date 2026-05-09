<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class MarkAsPaidAction
{
    public static function handle(): Action
    {
        return Action::make('markAsPaid')
            ->label('Mark as Paid')
            ->icon(Heroicon::CreditCard)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::CreditCard)
            ->modalDescription(fn(Invoice $record) => 'Marking invoice #' . $record->number . ' as paid.')
            ->visible(fn($record) => self::isVisible($record))
            ->action(function(Invoice $invoice, InvoiceService $invoiceService) {
                $invoiceService->markAsPaid($invoice);
            });
    }

    private static function isVisible(Invoice $record): bool
    {
        return collect([InvoiceStatus::Sent])->contains($record->status);
    }
}
