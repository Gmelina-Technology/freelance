<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class AcceptQuoteAction
{
    public static function handle()
    {
        return Action::make('acceptQuote')
            ->label('Accept Quote')
            ->icon(Heroicon::CheckCircle)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->color('success')
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Sent)
            ->action(function (Quote $record) {
                $record->update(['status' => QuoteStatus::Accepted]);

                Notification::make()
                    ->title('Quote Accepted')
                    ->body('The quote has been marked as accepted.')
                    ->success()
                    ->send();
            });
    }
}
