<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Mail\QuoteMailSent;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class PreviewQuoteAction
{
    public static function handle(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Draft)
            ->modalHeading('Preview quote email')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (Quote $record): View => self::content($record));
    }

    /**
     * Build the preview view shared by the Preview and Send modals.
     */
    public static function content(Quote $quote): View
    {
        $mail = new QuoteMailSent($quote);
        $hasClientEmail = filled($quote->client?->email);
        $envelope = $hasClientEmail ? $mail->envelope() : null;

        return view('filament.quotes.preview', [
            'quote' => $quote,
            'to' => collect($envelope?->to ?? [])->map(fn ($address): string => $address->name.' <'.$address->address.'>')->all(),
            'cc' => collect($envelope?->cc ?? [])->map(fn ($address): string => $address->name.' <'.$address->address.'>')->all(),
            'subject' => $envelope?->subject ?? "Quote #{$quote->number}",
            'body' => $mail->renderedBody(),
            'hasClientEmail' => $hasClientEmail,
            'hasTemplate' => $mail->hasEmailTemplate(),
        ]);
    }
}
