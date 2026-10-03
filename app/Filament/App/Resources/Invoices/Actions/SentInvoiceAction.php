<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class SentInvoiceAction
{
    public static function handle()
    {
        return Action::make('sent')
            ->label('Send')
            ->icon(Heroicon::Envelope)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->visible(fn ($record) => self::isVisible($record))
            ->action(function (Invoice $record, InvoiceService $invoiceService) {
                $invoiceService->send($record);

                Notification::make()
                    ->title('Invoice Sent')
                    ->body('The invoice has been marked as sent and an email notification has been sent to the client.')
                    ->success()
                    ->send();
            });
    }

    private static function isVisible($record): bool
    {
        return collect([
            InvoiceStatus::Draft,
        ])->contains($record->status);
    }
}
