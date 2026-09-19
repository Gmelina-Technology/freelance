<?php

namespace App\Filament\App\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteNotAcceptableException;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Models\Quote;
use App\Services\QuoteService;
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
            ->modalDescription('Accepting creates one task per line item on the project board.')
            ->color('success')
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Sent)
            ->action(function (Quote $record) {
                try {
                    $quote = app(QuoteService::class)->accept($record);
                } catch (QuoteNotAcceptableException $exception) {
                    Notification::make()
                        ->title('Quote cannot be accepted')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $count = $quote->tasks()->count();

                $notification = Notification::make()
                    ->title('Quote Accepted')
                    ->body($count.' '.str('task')->plural($count).' created on the project board.')
                    ->success();

                if ($quote->project_id) {
                    $notification->actions([
                        Action::make('viewBoard')
                            ->label('Open board')
                            ->url(ProjectResource::getUrl('view', ['record' => $quote->project_id])),
                    ]);
                }

                $notification->send();
            });
    }
}
