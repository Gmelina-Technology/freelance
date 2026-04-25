<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
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
