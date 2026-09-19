<?php

namespace App\Filament\App\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Concerns\HasQuoteSteps;
use App\Filament\App\Resources\Quotes\QuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;

class EditQuote extends EditRecord
{
    use HasQuoteSteps {
        HasQuoteSteps::getSteps insteadof HasWizard;
    }
    use HasWizard;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status === QuoteStatus::Declined) {
            $data['status'] = QuoteStatus::Draft->value;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateAmount();
    }
}
