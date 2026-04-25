<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice as InvoicePdf;

class TemporaryInvoicePreviewController extends Controller
{
    /**
     * Preview invoice as HTML
     * Temporary endpoint for testing invoice template
     * Usage: /temp/invoices/{id}/preview
     */
    public function previewHtml(Invoice $invoice)
    {
        $seller = new Party([
            'name' => $invoice->account->name,
            'address' => $invoice->account->address ?? '',
            'custom_fields' => [
                'email' => $invoice->account->email ?? '',
            ],
        ]);

        $buyer = new Party([
            'name' => $invoice->client->name,
            'custom_fields' => [
                'email' => $invoice->client->email ?? '',
                'phone' => $invoice->client->phone ?? '',
            ],
        ]);

        $logoPath = null;
        if ($invoice->account->logo_path) {
            $fullPath = storage_path('app/public/'.$invoice->account->logo_path);
            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            }
        }

        $filename = "Invoice-{$invoice->number}";

        $invoicePdf = InvoicePdf::make()
            ->logo($logoPath)
            ->seller($seller)
            ->buyer($buyer)
            ->date($invoice->issued_at)
            ->dateFormat('m/d/Y')
            ->payUntilDays(7)
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->filename($filename)
            ->status($invoice->status->value);

        // Add items from the invoice
        foreach ($invoice->items as $item) {
            $invoicePdf->addItem(
                InvoiceItem::make($item->task?->category?->name ?? 'Service')
                    ->description($item->task?->title ?? '')
                    ->units($item->unit)
                    ->quantity($item->quantity)
                    ->pricePerUnit($item->unit_price)
            );
        }

        // Add notes if available
        if ($invoice->notes) {
            $invoicePdf->notes($invoice->notes);
        }

        // Get the blade template content
        return view('vendor.invoices.templates.default', [
            'invoice' => $invoicePdf->render(),
        ]);
    }

    /**
     * Render invoice as PDF
     * Temporary endpoint for testing PDF generation
     * Usage: /temp/invoices/{id}/render
     */
    public function renderPdf(Invoice $invoice)
    {
        $seller = new Party([
            'name' => $invoice->account->name,
            'address' => $invoice->account->address ?? '',
            'custom_fields' => [
                'email' => $invoice->account->email ?? '',
            ],
        ]);

        $buyer = new Party([
            'name' => $invoice->client->name,
            'custom_fields' => [
                'email' => $invoice->client->email ?? '',
                'phone' => $invoice->client->phone ?? '',
            ],
        ]);

        $logoPath = null;
        if ($invoice->account->logo_path) {
            $fullPath = storage_path('app/public/'.$invoice->account->logo_path);
            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            }
        }

        $filename = "Invoice-{$invoice->number}";

        $invoicePdf = InvoicePdf::make()
            ->logo($logoPath)
            ->seller($seller)
            ->buyer($buyer)
            ->date($invoice->issued_at)
            ->dateFormat('m/d/Y')
            ->payUntilDays(7)
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->filename($filename)
            ->status($invoice->status->value);

        // Add items from the invoice
        foreach ($invoice->items as $item) {
            $invoicePdf->addItem(
                InvoiceItem::make($item->task?->category?->name ?? 'Service')
                    ->description($item->task?->title ?? '')
                    ->units($item->unit)
                    ->quantity($item->quantity)
                    ->pricePerUnit($item->unit_price)
            );
        }

        // Add notes if available
        if ($invoice->notes) {
            $invoicePdf->notes($invoice->notes);
        }

        // Return the PDF for download
        return $invoicePdf->stream();
    }
}
