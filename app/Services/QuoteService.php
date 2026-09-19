<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\QuoteNotAcceptableException;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Relaticle\Flowforge\Services\DecimalPosition;

class QuoteService
{
    public function __construct(public InvoiceService $invoiceService) {}

    /**
     * Generate a unique quote number.
     *
     * The account id is part of the number because quote numbers are globally unique.
     * The offset lets a retry skip past a number a concurrent request already took.
     */
    public static function generateQuoteNumber(int $accountId, int $offset = 0): string
    {
        $year = now()->year;
        $month = now()->month;

        $count = Quote::where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1 + $offset;

        return sprintf('Q%03d-%d%02d-%03d', $accountId, $year, $month, $count);
    }

    /**
     * Create a quote with a freshly generated number, retrying if a concurrent request took it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithNumber(array $attributes): Quote
    {
        return $this->invoiceService->retryOnDuplicateNumber(
            fn (int $attempt): Quote => DB::transaction(fn (): Quote => Quote::create([
                ...$attributes,
                'number' => self::generateQuoteNumber((int) $attributes['account_id'], $attempt),
            ])),
        );
    }

    /**
     * Accept a quote and generate one executable task per line item. Idempotent.
     *
     * @throws QuoteNotAcceptableException
     */
    public function accept(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $quote = Quote::withoutGlobalScopes()->lockForUpdate()->findOrFail($quote->getKey());

            if ($quote->status === QuoteStatus::Accepted) {
                return $quote;
            }

            if (! $quote->status->canTransitionTo(QuoteStatus::Accepted)) {
                throw new QuoteNotAcceptableException($quote);
            }

            foreach ($quote->items()->get() as $item) {
                if ($item->tasks()->withoutGlobalScopes()->exists()) {
                    continue;
                }

                // account_id is explicit: the tenant is not bound in jobs, tests or tinker.
                Task::create([
                    'account_id' => $quote->account_id,
                    'client_id' => $quote->client_id,
                    'project_id' => $quote->project_id,
                    'category_id' => $item->category_id,
                    'quote_item_id' => $item->getKey(),
                    'title' => $item->title,
                    'description' => $item->description,
                    'status' => TaskStatus::OPEN,
                    'priority' => TaskPriority::Medium,
                    'billing_status' => TaskBillingStatus::Billable,
                    'position' => $this->nextBoardPosition($quote),
                ]);
            }

            $quote->update([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $quote;
        });
    }

    /**
     * Position at the bottom of the project's backlog column on the kanban board.
     */
    private function nextBoardPosition(Quote $quote): string
    {
        $last = Task::withoutGlobalScopes()
            ->where('project_id', $quote->project_id)
            ->where('status', TaskStatus::OPEN)
            ->whereNotNull('position')
            ->orderByDesc('position')
            ->value('position');

        return $last === null
            ? DecimalPosition::forEmptyColumn()
            : DecimalPosition::after((string) $last);
    }
}
