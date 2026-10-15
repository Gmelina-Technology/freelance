<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Mail\InvitationEmail;
use App\Mail\InvoiceMailSent;
use App\Mail\QuoteMailSent;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('logs a failed queued job at error with its name and uuid', function () {
    Log::spy();

    try {
        dispatch(function () {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // The sync queue rethrows after raising the failure event.
    }

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Queued job failed'
        && array_key_exists('job', $context)
        && array_key_exists('uuid', $context)
        && str_contains($context['exception'], 'boom'))->once();
});

it('logs a failed invoice email at error and puts the invoice back to draft', function () {
    Log::spy();
    $account = Account::factory()->create();
    $client = Client::factory()->for($account)->create();
    $invoice = Invoice::factory()->for($account)->for($client)->create(['status' => InvoiceStatus::Sent]);

    (new InvoiceMailSent($invoice))->failed(new RuntimeException('smtp down'));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Failed to send invoice email'
        && $context['invoice_id'] === $invoice->id
        && $context['account_id'] === $account->id)->once();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('logs a failed quote email at error and puts the quote back to draft', function () {
    Log::spy();
    $scenario = makeQuoteScenario(status: QuoteStatus::Sent);

    (new QuoteMailSent($scenario['quote']))->failed(new RuntimeException('smtp down'));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Failed to send quote email'
        && $context['quote_id'] === $scenario['quote']->id
        && $context['account_id'] === $scenario['account']->id)->once();

    expect($scenario['quote']->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('logs a failed invitation email at error without the invitee address', function () {
    Log::spy();
    $invitation = Invitation::factory()->for(Account::factory()->for(User::factory(), 'owner'))->create(['email' => 'private.person@example.com']);

    (new InvitationEmail($invitation))->failed(new RuntimeException('550 private.person@example.com rejected'));

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($invitation): bool {
        return $message === 'Failed to send invitation email'
            && $context['invitation_id'] === $invitation->id
            && $context['account_id'] === $invitation->account_id
            && ! str_contains(json_encode($context), 'private.person@example.com');
    })->once();
});
