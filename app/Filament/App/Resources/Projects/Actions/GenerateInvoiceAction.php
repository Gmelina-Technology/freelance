<?php

namespace App\Filament\App\Resources\Projects\Actions;

use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Project;
use App\Models\Task;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
            ->requiresConfirmation()
            ->visible(fn (): bool => self::canGenerate())
            ->modalHeading('Generate invoice from completed tasks')
            ->modalDescription(fn (Project $record): string => self::describe($record))
            ->action(function (Project $record) {
                try {
                    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($record);
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

    private static function describe(Project $project): string
    {
        $tasks = app(InvoiceService::class)->billableTasksFor($project);

        if ($tasks->isEmpty()) {
            return 'There are no completed, billable tasks from accepted quotes to invoice yet.';
        }

        $total = $tasks->sum(fn (Task $task): float => (float) $task->quoteItem->quantity * (float) $task->quoteItem->unit_price);

        return sprintf(
            '%d completed %s will be billed at the quoted prices, %s in total. Tasks added ad hoc (not from a quote) are not included; invoice those with Create Invoice.',
            $tasks->count(),
            str('task')->plural($tasks->count()),
            Number::currency($total, $project->client?->currency_code ?: 'USD'),
        );
    }
}
