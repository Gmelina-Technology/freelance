<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class VoidInvoiceAction
{
    public static function handle()
    {
        return Action::make('void')
            ->label('Void')
            ->icon(Heroicon::ArchiveBox)
            ->color(Color::Red)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->visible(fn ($record) => self::isVisible($record))
            ->action(function (Invoice $record) {
                DB::transaction(function () use ($record) {
                    $record->update([
                        'status' => InvoiceStatus::Void,
                    ]);

                    app(InvoiceService::class)->releaseTasks($record);
                });

                Notification::make()
                    ->title('Invoice Voided')
                    ->body('The invoice has been voided successfully.')
                    ->success()
                    ->send();
            });
    }

    private static function isVisible($record): bool
    {
        return collect([
            InvoiceStatus::Draft,
            InvoiceStatus::Sent,
            InvoiceStatus::Overdue,
        ])->contains($record->status);
    }
}
