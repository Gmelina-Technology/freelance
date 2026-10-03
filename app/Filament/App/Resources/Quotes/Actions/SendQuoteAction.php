<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteTransitionException;
use App\Models\Quote;
use App\Services\QuoteService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class SendQuoteAction
{
    public static function handle()
    {
        return Action::make('send')
            ->label('Send Quote')
            ->icon(Heroicon::Envelope)
            ->iconPosition(IconPosition::After)
            ->requiresConfirmation()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (Quote $record): View => PreviewQuoteAction::content($record))
            ->visible(fn ($record) => $record->status === QuoteStatus::Draft)
            ->action(function (Quote $record) {
                try {
                    app(QuoteService::class)->send($record);
                } catch (QuoteTransitionException $exception) {
                    Notification::make()
                        ->title('Quote cannot be sent')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Quote Sent')
                    ->body('The quote has been marked as sent and an email has been sent to the client.')
                    ->success()
                    ->send();
            });
    }
}
