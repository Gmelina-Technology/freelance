<?php

namespace App\Filament\App\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Actions\AcceptQuoteAction;
use App\Filament\App\Resources\Quotes\Actions\ConvertToInvoiceAction;
use App\Filament\App\Resources\Quotes\Actions\DeclineQuoteAction;
use App\Filament\App\Resources\Quotes\Actions\SendQuoteAction;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendQuoteAction::handle(),
            AcceptQuoteAction::handle(),
            DeclineQuoteAction::handle(),
            ConvertToInvoiceAction::handle(),
            EditAction::make()
                ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Draft),
        ];
    }
}
