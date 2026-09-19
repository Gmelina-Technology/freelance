<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Mail\QuoteMailSent;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
                DB::transaction(function () use ($record) {
                    Mail::to($record->client->email, $record->client->name)
                        ->send(new QuoteMailSent($record));

                    $record->update(['status' => QuoteStatus::Sent]);

                    Notification::make()
                        ->title('Quote Sent')
                        ->body('The quote has been marked as sent and an email has been sent to the client.')
                        ->success()
                        ->send();
                });
            });
    }
}
