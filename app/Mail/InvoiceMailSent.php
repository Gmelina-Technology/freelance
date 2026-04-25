<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice as InvoicePdf;

class InvoiceMailSent extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice #{$this->invoice->number}",
            to: [$this->invoice->client->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-mail-sent',
            with: [
                'renderedContent' => $this->renderEmailContent(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        try {
            $pdfPath = $this->generateAndSaveInvoicePdf();

            return [
                Attachment::fromStorageDisk('local', $pdfPath)
                    ->as("Invoice-{$this->invoice->number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log error and return empty attachments if PDF generation fails
            Log::error("Failed to generate invoice PDF: {$e->getMessage()}");

            return [];
        }
    }

    private function generateAndSaveInvoicePdf(): string
    {
        $seller = new Party([
            'name' => $this->invoice->account->name,
            'address' => $this->invoice->account->address ?? '',
            'custom_fields' => [
                'email' => $this->invoice->account->email ?? '',
            ],
        ]);

        $client = $this->invoice->client;

        $buyer = new Party([
            'name' => $client->name,
            'custom_fields' => [
                'email' => $client->email ?? '',
                'phone' => $client->phone ?? '',
            ],
        ]);

        $logoPath = null;
        if ($this->invoice->account->logo_path) {
            $fullPath = storage_path('app/public/' . $this->invoice->account->logo_path);
            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            }
        }

        $filename = "Invoice-{$this->invoice->number}";

        $currency = $client->currency;

        $invoicePdf = InvoicePdf::make()
            ->logo($logoPath)
            ->seller($seller)
            ->buyer($buyer)
            ->date($this->invoice->issued_at)
            ->series($this->invoice->number)
            ->dateFormat('M d, Y')
            ->payUntilDays(7)
            ->currencySymbol($currency?->symbol ?? '$')
            ->currencyCode($currency?->code ?? 'USD')
            ->filename($filename)
            ->status($this->invoice->status->value);

        // Add items from the invoice
        foreach ($this->invoice->items as $item) {
            $invoicePdf->addItem(
                InvoiceItem::make($item->task?->category?->name ?? 'Service')
                    ->description($item->task?->title ?? '')
                    ->units($item->unit?->name)
                    ->quantity($item->quantity)
                    ->pricePerUnit($item->unit_price)
            );
        }

        // Add notes if available
        if ($this->invoice->notes) {
            $invoicePdf->notes($this->invoice->notes);
        }

        // Save the PDF to storage and return the path
        $invoicePdf->save('local');

        // Return the full path to the saved PDF file
        return $filename . '.pdf';
    }

    private function renderEmailContent(): string
    {
        $htmlContent = EmailTemplate::where('account_id', $this->invoice->account_id)
            ->where('type', 'INVOICE')
            ->value('body') ?? '';

        // Use Filament's RichContentRenderer to process merge tags
        // Merge tags use {{ tag }} format and are replaced with dynamic values
        return RichContentRenderer::make($htmlContent)
            ->mergeTags([
                'client_name' => $this->invoice->client->name ?? 'Client',
                'invoice_number' => $this->invoice->number ?? '',
                'invoice_amount' => $this->formatCurrency($this->invoice->amount),
                'issued_date' => $this->invoice->issued_at?->format('F j, Y') ?? now()->format('F j, Y'),
                'due_date' => $this->invoice->due_date?->format('F j, Y') ?? 'Not specified',
                'account_name' => $this->invoice->account->name ?? 'Account',
                'project_name' => $this->invoice->project->name ?? 'Project',
                'notes' => $this->invoice->notes ?? '',
            ])
            ->toHtml();
    }

    private function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return '$0.00';
        }

        return '$' . number_format((float) $amount, 2);
    }
}
