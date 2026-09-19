<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats numbers as Q{account}-{YYYYMM}-{seq}', function () {
    $account = Account::factory()->create();

    expect(QuoteService::generateQuoteNumber($account->id))
        ->toBe(sprintf('Q%03d-%s-001', $account->id, now()->format('Ym')));
});

it('increments the sequence per account within the month', function () {
    $account = Account::factory()->create();

    $first = app(QuoteService::class)->createWithNumber([
        'account_id' => $account->id,
        'client_id' => Client::factory()->for($account)->create()->id,
        'amount' => 0,
    ]);
    $second = app(QuoteService::class)->createWithNumber([
        'account_id' => $account->id,
        'client_id' => $first->client_id,
        'amount' => 0,
    ]);

    expect($first->number)->toEndWith('-001')
        ->and($second->number)->toEndWith('-002');
});

it('starts every account at 001', function () {
    $a = Account::factory()->create();
    $b = Account::factory()->create();

    Quote::factory()->for($a)->create();
    Quote::factory()->for($a)->create();

    expect(QuoteService::generateQuoteNumber($a->id))->toEndWith('-003')
        ->and(QuoteService::generateQuoteNumber($b->id))->toEndWith('-001')
        ->and(QuoteService::generateQuoteNumber($b->id))->not->toBe(QuoteService::generateQuoteNumber($a->id));
});
