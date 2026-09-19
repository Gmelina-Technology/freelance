<?php

namespace App\Filament\App\Resources\Quotes\Pages;

use App\Filament\App\Resources\Quotes\Concerns\HasQuoteSteps;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Services\QuoteService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;

class CreateQuote extends CreateRecord
{
    use HasQuoteSteps {
        HasQuoteSteps::getSteps insteadof HasWizard;
    }
    use HasWizard;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = QuoteResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            ...$this->data,
            'number' => QuoteService::generateQuoteNumber(Filament::getTenant()->id),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['account_id'] = Filament::getTenant()->getKey();

        return app(QuoteService::class)->createWithNumber($data);
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateAmount();
    }
}
