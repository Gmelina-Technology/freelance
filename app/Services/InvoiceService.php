<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskStatus;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Mail\InvoiceMailSent;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Mark the invoice paid, settle its tasks and record the sale.
     *
     * @param  bool  $onlyFromSent  reject any invoice that is not currently Sent
     *
     * @throws InvalidInvoiceTransitionException
     */
    public function markAsPaid(Invoice $invoice, bool $onlyFromSent = false): void
    {
        if ($onlyFromSent && $invoice->status !== InvoiceStatus::Sent) {
            throw new InvalidInvoiceTransitionException('Only a sent invoice can be marked as paid.');
        }

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
        });
    }

    /**
     * Email a draft invoice to its client and mark it sent.
     *
     * @throws InvalidInvoiceTransitionException
     */
    public function send(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvalidInvoiceTransitionException('Only a draft invoice can be sent.');
        }

        DB::transaction(function () use ($invoice) {
            $client = $invoice->client;

            Mail::to($client->email, $client->name)->send(new InvoiceMailSent($invoice));

            $invoice->update(['status' => InvoiceStatus::Sent]);
        });
    }

    /**
     * Void a draft, sent or overdue invoice and hand its tasks back to billing.
     *
     * @throws InvalidInvoiceTransitionException
     */
    public function void(Invoice $invoice): void
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Overdue], true)) {
            throw new InvalidInvoiceTransitionException('Only a draft, sent or overdue invoice can be voided.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => InvoiceStatus::Void]);

            $this->releaseTasks($invoice);
        });
    }

    /**
     * Create a draft invoice by hand from billable tasks of one project.
     *
     * @param  array{client_id: int, project_id: int, notes?: ?string, issued_at?: mixed, due_date?: mixed}  $attributes
     * @param  array<int, array{task_id: int, unit_id: int, quantity: float|int|string, unit_price: float|int|string}>  $items
     *
     * @throws ValidationException
     */
    public function createManual(Account $account, array $attributes, array $items): Invoice
    {
        $project = $account->projects()->whereKey($attributes['project_id'])->first();

        if ($project === null || (int) $project->client_id !== (int) $attributes['client_id']) {
            throw ValidationException::withMessages(['project_id' => 'The project does not belong to that client in this account.']);
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'An invoice needs at least one item.']);
        }

        $taskIds = collect($items)->pluck('task_id');

        if ($taskIds->unique()->count() !== $taskIds->count()) {
            throw ValidationException::withMessages(['items' => 'A task can only appear once on an invoice.']);
        }

        foreach ($items as $index => $item) {
            if (! Unit::query()->where('account_id', $account->getKey())->whereKey($item['unit_id'])->exists()) {
                throw ValidationException::withMessages(["items.{$index}.unit_id" => 'The unit was not found in this account.']);
            }
        }

        return $this->retryOnDuplicateNumber(function (int $attempt) use ($account, $project, $attributes, $items, $taskIds): Invoice {
            return DB::transaction(function () use ($account, $project, $attributes, $items, $taskIds, $attempt): Invoice {
                $billable = Task::query()
                    ->where('account_id', $account->getKey())
                    ->where('project_id', $project->getKey())
                    ->where('billing_status', TaskBillingStatus::Billable)
                    ->whereKey($taskIds)
                    ->lockForUpdate()
                    ->pluck('id');

                $unavailable = $taskIds->diff($billable);

                if ($unavailable->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'Tasks not billable on this project (or already invoiced): '.$unavailable->implode(', ').'.',
                    ]);
                }

                $invoice = Invoice::create([
                    'account_id' => $account->getKey(),
                    'client_id' => $project->client_id,
                    'project_id' => $project->getKey(),
                    'task_id' => null,
                    'number' => self::generateInvoiceNumber($account->getKey(), $attempt),
                    'amount' => 0,
                    'status' => InvoiceStatus::Draft,
                    'notes' => $attributes['notes'] ?? null,
                    'issued_at' => $attributes['issued_at'] ?? now(),
                    'due_date' => $attributes['due_date'] ?? now()->addWeekdays(7),
                ]);

                foreach ($items as $item) {
                    $invoice->items()->create([
                        'task_id' => $item['task_id'],
                        'unit_id' => $item['unit_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ]);
                }

                $this->finalizeManual($invoice);

                $invoice->update([
                    'amount' => round($invoice->items()->get()->sum(
                        fn ($item): float => (float) $item->quantity * (float) $item->unit_price
                    ), 2),
                ]);

                return $invoice;
            });
        });
    }

    /**
     * Claim the tasks of a hand-made invoice, settling them if it was created already paid.
     */
    public function finalizeManual(Invoice $invoice): void
    {
        $this->claimTasks($invoice);

        if ($invoice->status === InvoiceStatus::Paid) {
            $this->settleTasks($invoice);
        }
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
