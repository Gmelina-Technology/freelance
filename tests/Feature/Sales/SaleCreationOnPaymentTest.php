<?php

use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Invoices\MarkInvoicePaid;
use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\InvoiceService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

it('records the client currency and leaves the sale unconverted', function (?string $accountCurrencyCode, ?string $clientCurrencyCode) {
    $account = $accountCurrencyCode === null
        ? Account::factory()->create(['currency_code' => null])
        : Account::factory()->withCurrency($accountCurrencyCode)->create();
    $invoice = sentInvoiceFor($account, $clientCurrencyCode);

    app(InvoiceService::class)->markAsPaid($invoice);

    $sale = Sale::sole();

    expect($sale->currency_code)->toBe($clientCurrencyCode)
        ->and((float) $sale->amount)->toBe(1500.0)
        ->and($sale->isConverted())->toBeFalse()
        ->and(SaleConversion::count())->toBe(0);
})->with([
    'same currency' => ['EUR', 'EUR'],
    'foreign currency' => ['PHP', 'EUR'],
    'client without currency' => ['EUR', null],
    'account without currency' => [null, 'EUR'],
]);

it('does not convert a same currency sale marked paid from the invoices table', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->withCurrency('EUR')->for($owner, 'owner')->create();
    $invoice = sentInvoiceFor($account, 'EUR');
    bindFilamentTenant($owner, $account);

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('markAsPaid')->table($invoice))
        ->assertNotified('Invoice mark as paid');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Sale::sole()->currency_code)->toBe('EUR')
        ->and(SaleConversion::count())->toBe(0);
});

it('does not convert a same currency sale marked paid through MCP', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->withCurrency('EUR')->for($owner, 'owner')->create();
    $invoice = sentInvoiceFor($account, 'EUR');
    actingAsToken($owner, ['*'], $account);

    BillingServer::tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertOk();

    expect(Sale::sole()->currency_code)->toBe('EUR')
        ->and(SaleConversion::count())->toBe(0);
});
