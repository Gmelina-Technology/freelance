<?php

namespace App\Mail;

use App\Enums\EmailTemplateType;
use App\Enums\QuoteStatus;
use App\Models\Currency;
use App\Models\EmailTemplate;
use App\Models\Quote;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice as InvoicePdf;
use Throwable;

class QuoteMailSent extends Mailable implements ShouldQueue
{
    use Queueable;

    private ?EmailTemplate $emailTemplate = null;

    public function __construct(public Quote $quote)
    {
        $this->emailTemplate = EmailTemplate::where('account_id', $quote->account_id)
            ->where('type', EmailTemplateType::QUOTE_REQUEST)
            ->first();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quote #{$this->quote->number}",
            from: new Address(config('mail.from.address'), $this->quote->account->name ?? config('mail.from.name')),
            to: [new Address($this->quote->client->email, $this->quote->client->name)],
            cc: array_map(fn ($poc) => new Address($poc['email'], $poc['name']), $this->quote->client->pocs ?? []),
            replyTo: [
                new Address(
                    $this->quote->account->email ?? config('mail.from.address'),
                    $this->quote->account->name ?? config('mail.from.name')
                ),
            ]
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
            $pdfPath = $this->generateAndSaveQuotePdf();

            return [
                Attachment::fromStorageDisk('local', $pdfPath)
                    ->as("Quote-{$this->quote->number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log error and return empty attachments if PDF generation fails
            Log::error("Failed to generate quote PDF: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Handle a queued email's failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->quote->update([
            'status' => QuoteStatus::Draft,
        ]);

        Log::info('Failed to send email for quote: '.$this->quote->number);
    }

    private function generateAndSaveQuotePdf(): string
    {
        $seller = new Party([
            'name' => $this->quote->account->name,
            'address' => $this->quote->account->address ?? '',
            'custom_fields' => [
                'email' => $this->quote->account->email ?? '',
            ],
        ]);

        $client = $this->quote->client;

        $buyer = new Party([
            'name' => $client->name,
            'custom_fields' => [
                'email' => $client->email ?? '',
                'phone' => $client->phone ?? '',
            ],
        ]);

        $logoPath = '';
        if ($this->quote->account->logo) {
            $fullPath = storage_path('app/public/'.$this->quote->account->logo);
            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            }
        }

        $filename = "Quote-{$this->quote->number}";

        $currency = $client->currency;

        $quotePdf = InvoicePdf::make()
            ->logo($logoPath)
            ->seller($seller)
            ->buyer($buyer)
            ->date($this->quote->issued_at)
            ->series($this->quote->number)
            ->payUntilDays($this->quote->issued_at?->diffInDays($this->quote->valid_until) ?? 0)
            ->dateFormat('M d, Y')
            ->currencySymbol($currency?->symbol ?? '$')
            ->currencyCode($currency?->code ?? 'USD')
            ->filename($filename)
            ->status(QuoteStatus::Sent->value);

        // Add items from the quote
        foreach ($this->quote->items as $item) {
            $quotePdf->addItem(
                InvoiceItem::make($item->task?->category?->name ?? 'Service')
                    ->description($item->task?->title ?? '')
                    ->units($item->unit?->name)
                    ->quantity($item->quantity)
                    ->pricePerUnit($item->unit_price)
            );
        }

        // Add notes if available
        if ($this->quote->notes) {
            $quotePdf->notes($this->quote->notes);
        }

        // Save the PDF to storage and return the path
        $quotePdf->save('local');

        // Return the full path to the saved PDF file
        return $filename.'.pdf';
    }

    private function renderEmailContent(): string
    {

        // Use Filament's RichContentRenderer to process merge tags
        // Merge tags use {{ tag }} format and are replaced with dynamic values
        return RichContentRenderer::make($this->emailTemplate?->body ?? '')
            ->mergeTags([
                'quote_number' => $this->quote->number ?? '',
                'quote_amount' => $this->formatCurrency($this->quote->client->currency, $this->quote->amount),
                'issued_date' => $this->quote->issued_at?->format('F j, Y') ?? now()->format('F j, Y'),
                'valid_until' => $this->quote->valid_until?->format('F j, Y') ?? 'Not specified',
                'account_name' => $this->quote->account->name ?? 'Account',
                'project_name' => $this->quote->project->name ?? 'Project',
                'client_name' => $this->quote->client->name ?? 'Client',
                'notes' => $this->quote->notes ?? '',
            ])
            ->toHtml();
    }

    private function formatCurrency(Currency $currency, float|int|null $amount): string
    {
        $symbol = $currency->symbol ?? '$';
        if ($amount === null) {
            return $symbol.'0.00';
        }

        return $symbol.number_format((float) $amount, 2);
    }
}
