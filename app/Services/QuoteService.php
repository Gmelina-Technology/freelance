<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quote;

class QuoteService
{
    public function __construct(public InvoiceService $invoiceService) {}

    /**
     * Generate a unique quote number
     */
    public static function generateQuoteNumber(int $accountId): string
    {
        $year = now()->year;
        $month = now()->month;

        $count = Quote::where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('QTE-%d%02d-%03d', $year, $month, $count);
    }

    /**
     * Convert an accepted quote into a billable invoice.
     */
    public function convertToInvoice(Quote $quote): Invoice
    {
        return $this->invoiceService->createFromQuote($quote);
    }
}
