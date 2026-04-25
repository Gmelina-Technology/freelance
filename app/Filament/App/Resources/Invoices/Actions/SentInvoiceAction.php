<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMailSent;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            ->action(function (Invoice $record) {
                DB::transaction(function () use ($record) {
                    self::sentInvoiceEmailToClient($record);
                    $record->update(['status' => InvoiceStatus::Sent]);
                    Notification::make()
                        ->title('Invoice Sent')
                        ->body('The invoice has been marked as sent and an email notification has been sent to the client.')
                        ->success()
                        ->send();
                });
            });
    }

    private static function isVisible($record): bool
    {
        return collect([
            InvoiceStatus::Draft,
        ])->contains($record->status);
    }

    private static function sentInvoiceEmailToClient(Invoice $record)
    {

        $client = $record->client;

        Mail::to($client->email, $client->name)->send(new InvoiceMailSent($record));
    }
}
