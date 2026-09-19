<?php

use App\Enums\QuoteStatus;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskStatus;
use App\Exceptions\QuoteNotAcceptableException;
use App\Models\Category;
use App\Models\Task;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Relaticle\Flowforge\Services\DecimalPosition;

uses(RefreshDatabase::class);

it('creates one task per quote line carrying its details', function () {
    ['quote' => $quote, 'account' => $account, 'client' => $client, 'project' => $project] = makeQuoteScenario();
    $category = Category::factory()->create(['account_id' => $account->id]);
    $quote->items()->first()->update(['category_id' => $category->id, 'description' => 'Wireframes']);

    app(QuoteService::class)->accept($quote);

    $tasks = Task::query()->orderBy('id')->get();

    expect($tasks)->toHaveCount(3);

    $first = $tasks->first();

    expect($first->title)->toBe('Design')
        ->and($first->description)->toBe('Wireframes')
        ->and($first->quote_item_id)->toBe($quote->items()->first()->id)
        ->and($first->account_id)->toBe($account->id)
        ->and($first->client_id)->toBe($client->id)
        ->and($first->project_id)->toBe($project->id)
        ->and($first->category_id)->toBe($category->id)
        ->and($first->status)->toBe(TaskStatus::OPEN)
        ->and($first->billing_status)->toBe(TaskBillingStatus::Billable);
});

it('assigns non-null, strictly increasing board positions', function () {
    ['quote' => $quote] = makeQuoteScenario();

    app(QuoteService::class)->accept($quote);

    $positions = Task::query()->orderBy('id')->pluck('position')->all();

    expect($positions)->each->not->toBeNull()
        ->and(DecimalPosition::greaterThan((string) $positions[1], (string) $positions[0]))->toBeTrue()
        ->and(DecimalPosition::greaterThan((string) $positions[2], (string) $positions[1]))->toBeTrue();
});

it('creates tasks in the quote account when no tenant is bound', function () {
    ['quote' => $quote, 'account' => $account] = makeQuoteScenario();

    app(QuoteService::class)->accept($quote);

    expect(Task::query()->pluck('account_id')->unique()->all())->toBe([$account->id]);
});

it('marks the quote accepted with a timestamp', function () {
    ['quote' => $quote] = makeQuoteScenario();

    $accepted = app(QuoteService::class)->accept($quote);

    expect($accepted->status)->toBe(QuoteStatus::Accepted)
        ->and($accepted->accepted_at)->not->toBeNull();
});

it('is idempotent when accepted twice', function () {
    ['quote' => $quote] = makeQuoteScenario();

    app(QuoteService::class)->accept($quote);
    app(QuoteService::class)->accept($quote);

    expect(Task::count())->toBe(3);
});

it('refuses to accept a quote that cannot transition to accepted', function (QuoteStatus $status) {
    ['quote' => $quote] = makeQuoteScenario(status: $status);

    app(QuoteService::class)->accept($quote);
})->with([
    'declined' => QuoteStatus::Declined,
    'void' => QuoteStatus::Void,
    'expired' => QuoteStatus::Expired,
    'converted' => QuoteStatus::Converted,
])->throws(QuoteNotAcceptableException::class);

it('accepts a draft quote directly', function () {
    ['quote' => $quote] = makeQuoteScenario(status: QuoteStatus::Draft);

    expect(app(QuoteService::class)->accept($quote)->status)->toBe(QuoteStatus::Accepted)
        ->and(Task::count())->toBe(3);
});

it('accepts a quote without line items cleanly', function () {
    ['quote' => $quote] = makeQuoteScenario();
    $quote->items()->delete();

    $accepted = app(QuoteService::class)->accept($quote);

    expect($accepted->status)->toBe(QuoteStatus::Accepted)
        ->and(Task::count())->toBe(0);
});
