<?php

namespace App\Mcp\Tools\Invoices;

use App\Mcp\Tools\BaseTool;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;

/**
 * Shared lookups and serialization for the invoice tools.
 */
abstract class InvoiceTool extends BaseTool
{
    /**
     * Find an invoice of the account; invoices of other accounts count as missing.
     */
    protected function findInvoice(Account $account, mixed $invoiceId): Invoice
    {
        return Invoice::query()
            ->where('account_id', $account->getKey())
            ->findOrFail($invoiceId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Invoice $invoice, bool $withItems = false): array
    {
        $invoice->loadMissing('client:id,name', 'project:id,name');

        $data = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'amount' => (float) $invoice->amount,
            'client_id' => $invoice->client_id,
            'client_name' => $invoice->client?->name,
            'project_id' => $invoice->project_id,
            'project_name' => $invoice->project?->name,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_date' => $invoice->due_date?->toIso8601String(),
            'notes' => $invoice->notes,
        ];

        if ($withItems) {
            $data['items'] = $invoice->items()
                ->with('task:id,title', 'unit:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (InvoiceItem $item): array => [
                    'id' => $item->id,
                    'task_id' => $item->task_id,
                    'task_title' => $item->task?->title,
                    'unit_id' => $item->unit_id,
                    'unit' => $item->unit?->name,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'amount' => (float) $item->amount,
                ])
                ->all();
        }

        return $data;
    }
}
