<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\QuoteNotAcceptableException;
use App\Exceptions\QuoteTransitionException;
use App\Mail\QuoteMailSent;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
     * Create a quote with its line items and a freshly generated number.
     * Line amounts are computed by the database; the quote total is recalculated.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $attributes, array $items): Quote
    {
        return DB::transaction(function () use ($attributes, $items): Quote {
            $quote = $this->createWithNumber([...$attributes, 'status' => QuoteStatus::Draft, 'amount' => 0]);

            $this->replaceItems($quote, $items);

            return $quote->refresh();
        });
    }

    /**
     * Update an editable quote. Declined and expired quotes go back to draft when revised.
     * Passing items replaces all line items; null leaves them untouched.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $items
     *
     * @throws QuoteTransitionException
     */
    public function update(Quote $quote, array $attributes, ?array $items = null): Quote
    {
        return DB::transaction(function () use ($quote, $attributes, $items): Quote {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->getKey());

            if (! $quote->status->isEditable()) {
                throw QuoteTransitionException::notEditable($quote);
            }

            $quote->update([...$attributes, 'status' => $this->statusAfterEdit($quote)]);

            if ($items !== null) {
                $this->replaceItems($quote, $items);
            } else {
                $quote->recalculateAmount();
            }

            return $quote->refresh();
        });
    }

    /**
     * The status a quote has once it has been edited: declined and expired quotes are reopened as draft.
     */
    public function statusAfterEdit(Quote $quote): QuoteStatus
    {
        return in_array($quote->status, [QuoteStatus::Declined, QuoteStatus::Expired], true)
            ? QuoteStatus::Draft
            : $quote->status;
    }

    /**
     * Email the quote to its client and mark it sent. Draft quotes only.
     *
     * @throws QuoteTransitionException
     */
    public function send(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw QuoteTransitionException::notAllowed($quote, QuoteStatus::Sent);
        }

        if (blank($quote->client->email)) {
            throw QuoteTransitionException::missingClientEmail($quote);
        }

        DB::transaction(function () use ($quote): void {
            Mail::to($quote->client->email, $quote->client->name)
                ->send(new QuoteMailSent($quote));

            $quote->update(['status' => QuoteStatus::Sent]);
        });

        return $quote;
    }

    /**
     * @throws QuoteTransitionException
     */
    public function decline(Quote $quote): Quote
    {
        return $this->transition($quote, QuoteStatus::Declined);
    }

    /**
     * @throws QuoteTransitionException
     */
    public function void(Quote $quote): Quote
    {
        return $this->transition($quote, QuoteStatus::Void);
    }

    /**
     * @throws QuoteTransitionException
     */
    private function transition(Quote $quote, QuoteStatus $target): Quote
    {
        if (! $quote->status->canTransitionTo($target)) {
            throw QuoteTransitionException::notAllowed($quote, $target);
        }

        $quote->update(['status' => $target]);

        return $quote;
    }

    /**
     * Replace the quote's line items and recalculate its total.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(Quote $quote, array $items): void
    {
        $quote->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quote->items()->create([
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'unit_id' => $item['unit_id'],
                'category_id' => $item['category_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sort_order' => $index,
            ]);
        }

        $quote->recalculateAmount();
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
