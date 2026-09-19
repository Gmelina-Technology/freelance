<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class VoidQuoteAction
{
    public static function handle()
    {
        return Action::make('voidQuote')
            ->label('Void')
            ->icon(Heroicon::ArchiveBox)
            ->color(Color::Red)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->visible(fn (Quote $record): bool => $record->status->canTransitionTo(QuoteStatus::Void))
            ->action(function (Quote $record) {
                $record->update(['status' => QuoteStatus::Void]);

                Notification::make()
                    ->title('Quote Voided')
                    ->body('The quote has been voided.')
                    ->success()
                    ->send();
            });
    }
}
