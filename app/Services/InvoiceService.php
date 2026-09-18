<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Models\Invoice;
use App\Models\Quote;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function markAsPaid(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
            ]);

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
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(int $accountId): string
    {
        $year = now()->year;
        $month = now()->month;

        // Count invoices for this account in current month
        $count = Invoice::where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        // Format: 00{accountId}-YYYYMM-001
        return sprintf('%03d-%d%02d-%03d', $accountId, $year, $month, $count);
    }

    /**
     * Create a billable invoice from an accepted quote, copying its line items.
     */
    public function createFromQuote(Quote $quote): Invoice
    {
        if ($quote->status !== QuoteStatus::Accepted) {
            throw new InvalidArgumentException('Only accepted quotes can be converted to invoices.');
        }

        if (! $quote->items()->exists()) {
            throw new InvalidArgumentException('Cannot convert a quote without line items.');
        }

        return DB::transaction(function () use ($quote) {
            $invoice = Invoice::create([
                'account_id' => $quote->account_id,
                'project_id' => $quote->project_id,
                'task_id' => $quote->task_id,
                'client_id' => $quote->client_id,
                'number' => self::generateInvoiceNumber($quote->account_id),
                'amount' => $quote->amount,
                'status' => InvoiceStatus::Draft,
                'issued_at' => now(),
                'due_date' => $quote->valid_until,
                'notes' => $quote->notes,
            ]);

            foreach ($quote->items as $item) {
                $invoice->items()->create([
                    'task_id' => $item->task_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);
            }

            $quote->update([
                'status' => QuoteStatus::Converted,
                'invoice_ref' => $invoice->number,
            ]);

            return $invoice->fresh();
        });
    }
}
