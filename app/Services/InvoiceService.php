<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function markAsPaid(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::Paid
            ]);

            CreateSaleAction::handle([
                'account_id' => $invoice->account_id,
                'category' => 'Service',
                'reference_key' => $invoice->number,
                'amount' => $invoice->amount,
                'transaction_date' => now()
            ]);

            Notification::make('markAsPaid')
                ->success()
                ->title('Invoice mark as paid')
                ->body('Invoice #' . $invoice->number . ' was marked as paid.')
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
}
