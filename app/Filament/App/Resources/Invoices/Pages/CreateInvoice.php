<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\Invoices\Concerns\HasInvoiceSteps;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreateInvoice extends CreateRecord
{
    use HasInvoiceSteps {
        HasInvoiceSteps::getSteps insteadof HasWizard;
    }
    use HasWizard;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            ...$this->data,
            'number' => InvoiceService::generateInvoiceNumber(Filament::getTenant()->id),
        ]);
    }

    /**
     * Manually invoiced tasks are claimed too, so they cannot be billed a second time.
     */
    protected function afterCreate(): void
    {
        $service = app(InvoiceService::class);

        $service->claimTasks($this->record);

        if ($this->record->status === InvoiceStatus::Paid) {
            $service->settleTasks($this->record);
        }
    }
}
