<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class DeclineQuoteAction
{
    public static function handle()
    {
        return Action::make('declineQuote')
            ->label('Decline Quote')
            ->icon(Heroicon::XCircle)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->color('danger')
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Sent)
            ->action(function (Quote $record) {
                $record->update(['status' => QuoteStatus::Declined]);

                Notification::make()
                    ->title('Quote Declined')
                    ->body('The quote has been marked as declined.')
                    ->danger()
                    ->send();
            });
    }
}
