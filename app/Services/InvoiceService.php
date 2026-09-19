<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskStatus;
use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function markAsPaid(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
            ]);

            $this->settleTasks($invoice);

            CreateSaleAction::handle([
                'account_id' => $invoice->account_id,
                'category' => 'Service',
                'reference_key' => $invoice->number,
                'amount' => $invoice->amount,
                'transaction_date' => now(),
            ]);

            Notification::make('markAsPaid')
                ->success()
                ->title('Invoice mark as paid')
                ->body('Invoice #'.$invoice->number.' was marked as paid.')
                ->send();
        });
    }

    /**
     * Generate unique invoice number.
     *
     * The offset lets a retry skip past a number a concurrent request already took.
     */
    public static function generateInvoiceNumber(int $accountId, int $offset = 0): string
    {
        $year = now()->year;
        $month = now()->month;

        // Count invoices for this account in current month
        $count = Invoice::where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1 + $offset;

        // Format: 00{accountId}-YYYYMM-001
        return sprintf('%03d-%d%02d-%03d', $accountId, $year, $month, $count);
    }

    /**
     * Create a draft invoice from every completed, billable task on the project that belongs to
     * an accepted quote, billing each one at the price agreed on its quote line.
     *
     * @throws NoBillableTasksException
     */
    public function generateFromCompletedTasks(Project $project, ?array $taskIds = null): Invoice
    {
        return $this->retryOnDuplicateNumber(function (int $attempt) use ($project, $taskIds): Invoice {
            return DB::transaction(function () use ($project, $taskIds, $attempt): Invoice {
                $tasks = $this->billableTasksFor($project, lock: true, taskIds: $taskIds);

                if ($tasks->isEmpty()) {
                    throw new NoBillableTasksException($project);
                }

                $amount = $tasks->sum(fn (Task $task): float => (float) $task->quoteItem->quantity * (float) $task->quoteItem->unit_price);

                $invoice = Invoice::create([
                    'account_id' => $project->account_id,
                    'client_id' => $project->client_id,
                    'project_id' => $project->getKey(),
                    'task_id' => null,
                    'number' => self::generateInvoiceNumber($project->account_id, $attempt),
                    'amount' => round($amount, 2),
                    'status' => InvoiceStatus::Draft,
                    'issued_at' => now(),
                    'due_date' => now()->addWeekdays(7),
                ]);

                foreach ($tasks as $task) {
                    $invoice->items()->create([
                        'task_id' => $task->getKey(),
                        'quote_item_id' => $task->quote_item_id,
                        'unit_id' => $task->quoteItem->unit_id,
                        'quantity' => $task->quoteItem->quantity,
                        'unit_price' => $task->quoteItem->unit_price,
                    ]);
                }

                $this->claimTasks($invoice);

                return $invoice;
            });
        });
    }

    /**
     * Completed, billable tasks of accepted quotes, ordered by quote then line order.
     *
     * @return Collection<int, Task>
     */
    public function billableTasksFor(Project $project, bool $lock = false, ?array $taskIds = null): Collection
    {
        return Task::query()
            ->where('project_id', $project->getKey())
            ->where('status', TaskStatus::COMPLETED)
            ->where('billing_status', TaskBillingStatus::Billable)
            ->whereNotNull('quote_item_id')
            ->when($taskIds !== null, fn ($query) => $query->whereKey($taskIds))
            ->whereHas('quoteItem.quote', fn ($query) => $query->where('status', QuoteStatus::Accepted))
            ->with('quoteItem.quote')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()
            ->sortBy([
                fn (Task $a, Task $b): int => $a->quoteItem->quote_id <=> $b->quoteItem->quote_id,
                fn (Task $a, Task $b): int => $a->quoteItem->sort_order <=> $b->quoteItem->sort_order,
                fn (Task $a, Task $b): int => $a->getKey() <=> $b->getKey(),
            ])
            ->values();
    }

    /**
     * Billable -> pending payment for every task on the invoice.
     */
    public function claimTasks(Invoice $invoice): void
    {
        $this->transitionTasks($invoice, TaskBillingStatus::Billable, TaskBillingStatus::PendingPayment);
    }

    /**
     * Pending payment -> billable (invoice voided or deleted).
     */
    public function releaseTasks(Invoice $invoice): void
    {
        $this->transitionTasks($invoice, TaskBillingStatus::PendingPayment, TaskBillingStatus::Billable);
    }

    /**
     * Pending payment -> paid (invoice paid).
     */
    public function settleTasks(Invoice $invoice): void
    {
        $this->transitionTasks($invoice, TaskBillingStatus::PendingPayment, TaskBillingStatus::Paid);
    }

    /**
     * Re-sync claims after a draft invoice's lines were edited, and recalculate its total.
     *
     * @param  Collection<int, int>  $previousTaskIds  task ids that were on the invoice before the edit
     */
    public function resyncTasks(Invoice $invoice, Collection $previousTaskIds): void
    {
        DB::transaction(function () use ($invoice, $previousTaskIds): void {
            if ($previousTaskIds->isNotEmpty()) {
                Task::query()
                    ->whereIn('id', $previousTaskIds)
                    ->where('billing_status', TaskBillingStatus::PendingPayment)
                    ->update(['billing_status' => TaskBillingStatus::Billable]);
            }

            $this->claimTasks($invoice);

            $invoice->update([
                'amount' => round($invoice->items()->get()->sum(
                    fn ($item): float => (float) $item->quantity * (float) $item->unit_price
                ), 2),
            ]);
        });
    }

    private function transitionTasks(Invoice $invoice, TaskBillingStatus $from, TaskBillingStatus $to): void
    {
        $taskIds = $invoice->items()->pluck('task_id')->filter();

        if ($taskIds->isEmpty()) {
            return;
        }

        Task::query()
            ->whereIn('id', $taskIds)
            ->where('billing_status', $from)
            ->update(['billing_status' => $to]);
    }

    /**
     * Run a numbered write, retrying a bounded number of times if a concurrent request took the number.
     *
     * @template T
     *
     * @param  callable(int): T  $callback  receives the 0-based attempt index, used to offset the sequence
     * @return T
     */
    public function retryOnDuplicateNumber(callable $callback, int $attempts = 3): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return $callback($attempt);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt + 1 >= $attempts) {
                    throw $exception;
                }
            }
        }
    }
}
