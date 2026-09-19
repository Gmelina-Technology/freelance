<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\Concerns\HasInvoiceSteps;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Illuminate\Support\Collection;

class EditInvoice extends EditRecord
{
    use HasInvoiceSteps {
        HasInvoiceSteps::getSteps insteadof HasWizard;
    }
    use HasWizard;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = InvoiceResource::class;

    /** @var Collection<int, int> */
    protected Collection $previousTaskIds;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->previousTaskIds = $this->record->items()->pluck('task_id')->filter();
    }

    /**
     * Keep task billing states in step with the edited lines: removed lines return to billable,
     * added lines are claimed, and the total is recalculated from the lines.
     */
    protected function afterSave(): void
    {
        app(InvoiceService::class)->resyncTasks($this->record, $this->previousTaskIds);
    }
}
