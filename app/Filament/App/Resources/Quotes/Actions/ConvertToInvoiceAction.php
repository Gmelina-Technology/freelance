<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Services\QuoteService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class ConvertToInvoiceAction
{
    public static function handle()
    {
        return Action::make('convertToInvoice')
            ->label('Convert to Invoice')
            ->icon(Heroicon::ArrowRightCircle)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Accepted)
            ->action(function (Quote $record) {
                $invoice = app(QuoteService::class)->convertToInvoice($record);

                Notification::make()
                    ->title('Quote Converted')
                    ->body('The quote has been converted to invoice #'.$invoice->number.'.')
                    ->success()
                    ->send();
            });
    }
}
