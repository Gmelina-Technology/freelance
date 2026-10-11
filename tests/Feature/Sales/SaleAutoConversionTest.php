<?php

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\InvoiceService;
use App\Services\SaleConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
});

function sentInvoiceFor(Account $account, ?string $clientCurrencyCode): Invoice
{
    $client = Client::factory()->for($account)->create(['currency_code' => $clientCurrencyCode]);

    return Invoice::factory()->for($account)->for($client)->create([
        'status' => InvoiceStatus::Sent,
        'amount' => 1500,
    ]);
}

it('converts the sale at 1:1 when the client pays in the account currency', function () {
    $account = Account::factory()->withCurrency('EUR')->create();
    $invoice = sentInvoiceFor($account, 'EUR');

    app(InvoiceService::class)->markAsPaid($invoice);

    $sale = Sale::sole();

    expect($sale->currency_code)->toBe('EUR')
        ->and($sale->isConverted())->toBeTrue()
        ->and($sale->converted_currency_code)->toBe('EUR')
        ->and($sale->converted_amount)->toBe('1500.00')
        ->and($sale->gross_amount)->toBe('1500.00')
        ->and($sale->exchange_rate)->toBe('1.0000000000')
        ->and($sale->converted_by)->toBeNull();
});

it('records the client currency but leaves a foreign currency sale unconverted', function () {
    $account = Account::factory()->withCurrency('PHP')->create();
    $invoice = sentInvoiceFor($account, 'EUR');

    app(InvoiceService::class)->markAsPaid($invoice);

    $sale = Sale::sole();

    expect($sale->currency_code)->toBe('EUR')
        ->and($sale->isConverted())->toBeFalse()
        ->and($sale->converted_amount)->toBeNull();
});

it('leaves the currency unknown when the client has none', function () {
    $account = Account::factory()->withCurrency('EUR')->create();
    $invoice = sentInvoiceFor($account, null);

    app(InvoiceService::class)->markAsPaid($invoice);

    expect(Sale::sole()->currency_code)->toBeNull()
        ->and(Sale::sole()->isConverted())->toBeFalse();
});

it('does not convert while the account has no currency', function () {
    $account = Account::factory()->create(['currency_code' => null]);
    $invoice = sentInvoiceFor($account, 'EUR');

    app(InvoiceService::class)->markAsPaid($invoice);

    expect(Sale::sole()->currency_code)->toBe('EUR')
        ->and(Sale::sole()->isConverted())->toBeFalse();
});

it('rolls back the paid status when recording the sale fails', function () {
    $account = Account::factory()->withCurrency('EUR')->create();
    $invoice = sentInvoiceFor($account, 'EUR');

    $this->mock(SaleConversionService::class)
        ->shouldReceive('convertAtParIfSameCurrency')
        ->andThrow(new RuntimeException('Conversion failed'));

    expect(fn () => app(InvoiceService::class)->markAsPaid($invoice))->toThrow(RuntimeException::class);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and(Sale::count())->toBe(0);
});
