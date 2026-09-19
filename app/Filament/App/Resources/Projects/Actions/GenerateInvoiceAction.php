<?php

namespace App\Filament\App\Resources\Projects\Actions;

use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Project;
use App\Models\Task;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class GenerateInvoiceAction
{
    /**
     * Members can reach the project board, so the action is restricted to owners and managers.
     */
    public static function canGenerate(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }

    public static function handle(): Action
    {
        return Action::make('generateInvoice')
            ->label('Generate Invoice')
            ->icon(Heroicon::DocumentCurrencyDollar)
            ->visible(fn (): bool => self::canGenerate())
            ->modalHeading('Generate invoice from completed tasks')
            ->modalDescription('Choose the tasks to bill at their quoted prices. Tasks added ad hoc (not from a quote) are not listed; invoice those with Create Invoice.')
            ->modalSubmitActionLabel('Generate invoice')
            ->schema([
                CheckboxList::make('task_ids')
                    ->label('Billable tasks')
                    ->options(fn (Project $record): array => self::options($record))
                    ->default(fn (Project $record): array => array_keys(self::options($record)))
                    ->bulkToggleable()
                    ->required()
                    ->validationMessages(['required' => 'Select at least one task to bill.'])
                    ->columns(1),
            ])
            ->action(function (array $data, Project $record) {
                try {
                    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($record, array_map('intval', $data['task_ids']));
                } catch (NoBillableTasksException) {
                    Notification::make()
                        ->title('Nothing to bill')
                        ->body('There are no completed, billable tasks from accepted quotes on this project.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Invoice generated')
                    ->body('Draft invoice #'.$invoice->number.' was created.')
                    ->success()
                    ->actions([
                        Action::make('viewInvoice')
                            ->label('View invoice')
                            ->url(ViewInvoice::getUrl(['record' => $invoice])),
                    ])
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private static function options(Project $project): array
    {
        $currency = $project->client?->currency_code ?: 'USD';

        return app(InvoiceService::class)->billableTasksFor($project)
            ->mapWithKeys(fn (Task $task): array => [
                $task->getKey() => sprintf(
                    '%s (%s) — %s',
                    $task->title,
                    $task->quoteItem->quote->number,
                    Number::currency((float) $task->quoteItem->quantity * (float) $task->quoteItem->unit_price, $currency),
                ),
            ])
            ->all();
    }
}
