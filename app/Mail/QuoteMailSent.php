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
                'renderedContent' => $this->renderedBody(),
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
            if ($this->usesCustomAttachment()) {
                return [
                    Attachment::fromStorageDisk(Quote::ATTACHMENT_DISK, $this->quote->attachment_path)
                        ->as($this->attachmentName())
                        ->withMime('application/pdf'),
                ];
            }

            $pdfPath = $this->generateAndSaveQuotePdf();

            return [
                Attachment::fromStorageDisk('local', $pdfPath)
                    ->as($this->attachmentName())
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log error and return empty attachments if PDF generation fails
            Log::error("Failed to generate quote PDF: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Whether the uploaded quote PDF is attached instead of the generated one:
     * the template must allow it and a file must actually be stored for the quote.
     */
    public function usesCustomAttachment(): bool
    {
        return $this->emailTemplate?->featureEnabled('custom_attachment') === true
            && $this->quote->hasStoredAttachment();
    }

    /**
     * The file name the client sees for the attached PDF.
     */
    public function attachmentName(): string
    {
        return "Quote-{$this->quote->number}.pdf";
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

    /**
     * Whether the account has a QUOTE_REQUEST email template to render the body from.
     */
    public function hasEmailTemplate(): bool
    {
        return $this->emailTemplate !== null;
    }

    /**
     * Build the (unsaved) quote PDF exactly as it is attached to the email.
     */
    public function buildPdf(): InvoicePdf
    {
        $this->quote->loadMissing(['account', 'client.currency', 'items.unit', 'items.category', 'items.task.category', 'project']);

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
            ->template('quote')
            ->setCustomData([
                'valid_until' => $this->quote->valid_until?->format('M d, Y'),
                'project_name' => $this->quote->project?->name,
            ])
            ->dateFormat('M d, Y')
            ->currencySymbol($currency?->symbol ?? '$')
            ->currencyCode($currency?->code ?? 'USD')
            ->filename($filename);

        // Add items from the quote
        foreach ($this->quote->items as $item) {
            $quotePdf->addItem(
                InvoiceItem::make($item->title ?? $item->task?->title ?? 'Service')
                    ->description(implode(' - ', array_filter([
                        $item->category?->name ?? $item->task?->category?->name,
                        $item->description ?? ($item->title === null ? null : $item->task?->title),
                    ])))
                    ->units($item->unit?->name)
                    ->quantity($item->quantity)
                    ->pricePerUnit($item->unit_price)
            );
        }

        // Add notes if available
        if ($this->quote->notes) {
            $quotePdf->notes($this->quote->notes);
        }

        return $quotePdf;
    }

    private function generateAndSaveQuotePdf(): string
    {
        $quotePdf = $this->buildPdf();

        $quotePdf->save('local');

        return $quotePdf->filename;
    }

    public function renderedBody(): string
    {
        if (blank($this->emailTemplate?->body)) {
            return '';
        }

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

    private function formatCurrency(?Currency $currency, float|int|null $amount): string
    {
        $symbol = $currency?->symbol ?? '$';
        if ($amount === null) {
            return $symbol.'0.00';
        }

        return $symbol.number_format((float) $amount, 2);
    }
}
