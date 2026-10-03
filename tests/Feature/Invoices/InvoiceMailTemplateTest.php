<?php

use App\Mail\InvoiceMailSent;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the invoice email when the account has no invoice email template', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->for($account)->create(['email' => 'client@example.test']);
    $invoice = Invoice::factory()->for($account)->for($client)->create();

    $html = (new InvoiceMailSent($invoice))->render();

    expect($html)->toBeString();
});
