<?php

use App\Enums\QuoteStatus;
use App\Mail\QuoteMailSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice as InvoicePdf;

uses(RefreshDatabase::class);

it('renders the quote pdf with quote wording and no invoice wording', function () {
    ['quote' => $quote, 'project' => $project] = makeQuoteScenario([
        ['title' => 'Logo design', 'quantity' => 1, 'unit_price' => 100],
    ], QuoteStatus::Draft);
    $quote->update(['valid_until' => now()->addDays(14)]);

    $pdf = (new QuoteMailSent($quote->fresh()))->buildPdf();
    $pdf->render();
    $html = $pdf->toHtml()->render();

    expect($pdf->template)->toBe('quote')
        ->and($html)->toContain('QUOTE')
        ->toContain('Quote No.')
        ->toContain($quote->number)
        ->toContain('Valid until')
        ->toContain(now()->addDays(14)->format('M d, Y'))
        ->toContain('Prepared for')
        ->toContain('Logo design')
        ->toContain($project->name)
        ->not->toContain('INVOICE')
        ->not->toContain('Pay until')
        ->not->toContain('Paid');
});

it('omits the valid until line when the quote has no expiry', function () {
    ['quote' => $quote] = makeQuoteScenario(status: QuoteStatus::Draft);
    $quote->update(['valid_until' => null]);

    $pdf = (new QuoteMailSent($quote->fresh()))->buildPdf();
    $pdf->render();

    expect($pdf->toHtml()->render())->not->toContain('Valid until');
});

it('still renders invoices with the default template', function () {
    $pdf = InvoicePdf::make()
        ->seller(new Party(['name' => 'Seller']))
        ->buyer(new Party(['name' => 'Buyer']))
        ->series('INV-1')
        ->addItem(InvoiceItem::make('Work')->quantity(1)->pricePerUnit(10));
    $pdf->render();

    expect($pdf->template)->toBe('default')
        ->and($pdf->toHtml()->render())->toContain('INVOICE')->not->toContain('QUOTE');
});
