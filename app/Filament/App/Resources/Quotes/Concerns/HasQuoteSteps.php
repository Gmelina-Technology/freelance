<?php

namespace App\Filament\App\Resources\Quotes\Concerns;

use App\Filament\App\Resources\Quotes\Schemas\QuoteForm;
use Filament\Schemas\Components\Wizard\Step;

trait HasQuoteSteps
{
    protected function getSteps(): array
    {
        return [
            Step::make('Details')
                ->schema(QuoteForm::detailsComponents())
                ->columns(7),
            Step::make('Quote Items')
                ->schema(QuoteForm::itemComponents()),
        ];
    }
}
