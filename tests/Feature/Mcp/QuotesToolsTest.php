<?php

use App\Enums\AccountRole;
use App\Enums\QuoteStatus;
use App\Mail\QuoteMailSent;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Quotes\AcceptQuote;
use App\Mcp\Tools\Quotes\CreateQuote;
use App\Mcp\Tools\Quotes\DeclineQuote;
use App\Mcp\Tools\Quotes\GetQuote;
use App\Mcp\Tools\Quotes\GetQuotePdf;
use App\Mcp\Tools\Quotes\ListQuotes;
use App\Mcp\Tools\Quotes\SendQuote;
use App\Mcp\Tools\Quotes\UpdateQuote;
use App\Mcp\Tools\Quotes\VoidQuote;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->client = Client::factory()->for($this->account)->create();
    $this->project = Project::factory()->for($this->account)->for($this->client, 'client')->create();
    $this->unit = Unit::factory()->for($this->account)->create();
    $this->category = Category::factory()->for($this->account)->create();

    Sanctum::actingAs($this->owner, ['*']);
});

function quoteItems(Unit $unit, array $overrides = []): array
{
    return [[
        'title' => 'Design',
        'unit_id' => $unit->id,
        'quantity' => 2,
        'unit_price' => 100,
        ...$overrides,
    ], [
        'title' => 'Build',
        'unit_id' => $unit->id,
        'quantity' => 1.5,
        'unit_price' => 80,
    ]];
}

it('creates a draft quote with items and a computed total', function () {
    BillingServer::tool(CreateQuote::class, [
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'items' => quoteItems($this->unit, ['category_id' => $this->category->id, 'description' => 'Wireframes']),
        'notes' => 'Thanks',
    ])->assertOk()->assertHasNoErrors();

    $quote = Quote::query()->firstOrFail();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->account_id)->toBe($this->account->id)
        ->and($quote->number)->not->toBeEmpty()
        ->and($quote->notes)->toBe('Thanks')
        ->and((float) $quote->amount)->toBe(320.0)
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->items->first()->category_id)->toBe($this->category->id)
        ->and($quote->items->pluck('sort_order')->all())->toBe([0, 1])
        ->and((float) $quote->items->first()->amount)->toBe(200.0);
});

it('rejects a quote without items', function () {
    BillingServer::tool(CreateQuote::class, [
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'items' => [],
    ])->assertHasErrors(['Validation failed']);

    expect(Quote::count())->toBe(0);
});

it('rejects a project that belongs to another client', function () {
    $otherProject = Project::factory()->for($this->account)->for(Client::factory()->for($this->account), 'client')->create();

    BillingServer::tool(CreateQuote::class, [
        'client_id' => $this->client->id,
        'project_id' => $otherProject->id,
        'items' => quoteItems($this->unit),
    ])->assertHasErrors(['Validation failed']);
});

it('rejects clients, units and categories from another account', function () {
    $foreign = Account::factory()->for(User::factory()->create(), 'owner')->create();
    $foreignClient = Client::factory()->for($foreign)->create();
    $foreignProject = Project::factory()->for($foreign)->for($foreignClient, 'client')->create();
    $foreignUnit = Unit::factory()->for($foreign)->create();
    $foreignCategory = Category::factory()->for($foreign)->create();

    BillingServer::tool(CreateQuote::class, [
        'client_id' => $foreignClient->id,
        'project_id' => $foreignProject->id,
        'items' => quoteItems($this->unit),
    ])->assertHasErrors(['Validation failed']);

    BillingServer::tool(CreateQuote::class, [
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'items' => quoteItems($foreignUnit),
    ])->assertHasErrors(['Validation failed']);

    BillingServer::tool(CreateQuote::class, [
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'items' => quoteItems($this->unit, ['category_id' => $foreignCategory->id]),
    ])->assertHasErrors(['Validation failed']);

    expect(Quote::count())->toBe(0);
});

it('lists quotes filtered by status, client and project', function () {
    Quote::factory()->forProject($this->project)->create(['number' => 'Q-DRAFT']);
    Quote::factory()->forProject($this->project)->sent()->create(['number' => 'Q-SENT']);
    $otherProject = Project::factory()->for($this->account)->for($this->client, 'client')->create();
    Quote::factory()->forProject($otherProject)->create(['number' => 'Q-OTHER']);

    BillingServer::tool(ListQuotes::class)
        ->assertSee(['Q-DRAFT', 'Q-SENT', 'Q-OTHER']);

    BillingServer::tool(ListQuotes::class, ['status' => 'sent'])
        ->assertSee('Q-SENT')
        ->assertDontSee(['Q-DRAFT', 'Q-OTHER']);

    BillingServer::tool(ListQuotes::class, ['project_id' => $otherProject->id])
        ->assertSee('Q-OTHER')
        ->assertDontSee(['Q-DRAFT', 'Q-SENT']);

    BillingServer::tool(ListQuotes::class, ['client_id' => $this->client->id, 'status' => 'draft'])
        ->assertSee('Q-DRAFT')
        ->assertDontSee('Q-SENT');

    BillingServer::tool(ListQuotes::class, ['status' => 'bogus'])->assertHasErrors(['Validation failed']);

});

it('shows a quote and its line items', function () {
    $scenario = makeQuoteScenario();
    Sanctum::actingAs($scenario['owner'], ['*']);

    BillingServer::tool(GetQuote::class, ['quote_id' => $scenario['quote']->id])
        ->assertOk()
        ->assertSee(['Design', 'Build', 'Launch', $scenario['quote']->number]);
});

it('does not expose quotes from another account', function () {
    $scenario = makeQuoteScenario();

    BillingServer::tool(GetQuote::class, ['quote_id' => $scenario['quote']->id])
        ->assertHasErrors(['not found in this account']);

    BillingServer::tool(ListQuotes::class)->assertDontSee($scenario['quote']->number);

    foreach ([UpdateQuote::class, SendQuote::class, AcceptQuote::class, DeclineQuote::class, VoidQuote::class, GetQuotePdf::class] as $tool) {
        BillingServer::tool($tool, ['quote_id' => $scenario['quote']->id])
            ->assertHasErrors(['not found in this account']);
    }

    expect($scenario['quote']->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('updates a draft quote and replaces its items', function () {
    $quote = app(QuoteService::class)->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
    ], quoteItems($this->unit));

    BillingServer::tool(UpdateQuote::class, [
        'quote_id' => $quote->id,
        'notes' => 'Revised',
        'items' => [['title' => 'Only item', 'unit_id' => $this->unit->id, 'quantity' => 3, 'unit_price' => 10]],
    ])->assertOk()->assertHasNoErrors();

    $quote->refresh();

    expect($quote->notes)->toBe('Revised')
        ->and($quote->items)->toHaveCount(1)
        ->and((float) $quote->amount)->toBe(30.0);
});

it('keeps items when updating without them', function () {
    $quote = app(QuoteService::class)->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
    ], quoteItems($this->unit));

    BillingServer::tool(UpdateQuote::class, ['quote_id' => $quote->id, 'notes' => 'Only notes'])->assertOk();

    expect($quote->fresh()->items)->toHaveCount(2)
        ->and((float) $quote->fresh()->amount)->toBe(320.0);
});

it('reopens a declined quote as draft when it is edited', function () {
    $quote = Quote::factory()->forProject($this->project)->create(['status' => QuoteStatus::Declined]);

    BillingServer::tool(UpdateQuote::class, ['quote_id' => $quote->id, 'notes' => 'Better offer'])->assertHasNoErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('refuses to edit quotes that are sent, accepted or void', function (QuoteStatus $status) {
    $quote = Quote::factory()->forProject($this->project)->create(['status' => $status]);

    BillingServer::tool(UpdateQuote::class, ['quote_id' => $quote->id, 'notes' => 'Nope'])
        ->assertHasErrors(['can no longer be edited']);

    expect($quote->fresh()->notes)->not->toBe('Nope');
})->with([QuoteStatus::Sent, QuoteStatus::Accepted, QuoteStatus::Void]);

it('requires a project when moving a quote to another client', function () {
    $quote = Quote::factory()->forProject($this->project)->create();
    $otherClient = Client::factory()->for($this->account)->create();

    BillingServer::tool(UpdateQuote::class, ['quote_id' => $quote->id, 'client_id' => $otherClient->id])
        ->assertHasErrors(['project_id']);
});

it('sends a draft quote by email and marks it sent', function () {
    Mail::fake();
    $quote = Quote::factory()->forProject($this->project)->create();

    BillingServer::tool(SendQuote::class, ['quote_id' => $quote->id])->assertOk()->assertHasNoErrors();

    Mail::assertQueued(QuoteMailSent::class, fn (QuoteMailSent $mail): bool => $mail->hasTo($this->client->email));
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('does not send a quote that is not a draft', function () {
    Mail::fake();
    $quote = Quote::factory()->forProject($this->project)->sent()->create();

    BillingServer::tool(SendQuote::class, ['quote_id' => $quote->id])
        ->assertHasErrors(['cannot move from "sent" to "sent"']);

    Mail::assertNothingQueued();
});

it('does not send a quote whose client has no email', function () {
    Mail::fake();
    $this->client->update(['email' => null]);
    $quote = Quote::factory()->forProject($this->project)->create();

    BillingServer::tool(SendQuote::class, ['quote_id' => $quote->id])
        ->assertHasErrors(['no email address']);

    Mail::assertNothingQueued();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('accepts a sent quote and creates one task per item', function () {
    ['quote' => $quote, 'owner' => $owner] = makeQuoteScenario();
    Sanctum::actingAs($owner, ['*']);

    BillingServer::tool(AcceptQuote::class, ['quote_id' => $quote->id])
        ->assertOk()
        ->assertSee(['Design', 'Build', 'Launch']);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and(Task::where('project_id', $quote->project_id)->count())->toBe(3);

    BillingServer::tool(AcceptQuote::class, ['quote_id' => $quote->id])->assertHasNoErrors();

    expect(Task::where('project_id', $quote->project_id)->count())->toBe(3);
});

it('does not accept declined or void quotes', function (QuoteStatus $status) {
    ['quote' => $quote, 'owner' => $owner] = makeQuoteScenario(status: $status);
    Sanctum::actingAs($owner, ['*']);

    BillingServer::tool(AcceptQuote::class, ['quote_id' => $quote->id])
        ->assertHasErrors(['cannot be accepted']);

    expect(Task::count())->toBe(0);
})->with([QuoteStatus::Declined, QuoteStatus::Void]);

it('declines a sent quote only', function () {
    $sent = Quote::factory()->forProject($this->project)->sent()->create();
    $draft = Quote::factory()->forProject($this->project)->create();

    BillingServer::tool(DeclineQuote::class, ['quote_id' => $sent->id])->assertHasNoErrors();
    BillingServer::tool(DeclineQuote::class, ['quote_id' => $draft->id])
        ->assertHasErrors(['cannot move from "draft" to "declined"']);

    expect($sent->fresh()->status)->toBe(QuoteStatus::Declined)
        ->and($draft->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('voids draft and sent quotes but not accepted ones', function () {
    $draft = Quote::factory()->forProject($this->project)->create();
    $accepted = Quote::factory()->forProject($this->project)->accepted()->create();

    BillingServer::tool(VoidQuote::class, ['quote_id' => $draft->id])->assertHasNoErrors();
    BillingServer::tool(VoidQuote::class, ['quote_id' => $accepted->id])
        ->assertHasErrors(['cannot move from "accepted" to "void"']);

    expect($draft->fresh()->status)->toBe(QuoteStatus::Void)
        ->and($accepted->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('returns the quote pdf as base64', function () {
    ['quote' => $quote, 'owner' => $owner] = makeQuoteScenario();
    Sanctum::actingAs($owner, ['*']);

    $response = BillingServer::tool(GetQuotePdf::class, ['quote_id' => $quote->id])->assertOk()->assertHasNoErrors();

    $response->assertSee(['content_base64', 'application/pdf', "Quote-{$quote->number}.pdf"]);
});

it('requires the quotes:write ability for writes only', function () {
    $quote = Quote::factory()->forProject($this->project)->create();
    Sanctum::actingAs($this->owner, ['tasks:write']);

    foreach ([CreateQuote::class, UpdateQuote::class, SendQuote::class, AcceptQuote::class, DeclineQuote::class, VoidQuote::class] as $tool) {
        BillingServer::tool($tool, ['quote_id' => $quote->id])->assertHasErrors(['quotes:write']);
    }

    BillingServer::tool(ListQuotes::class)->assertHasNoErrors();
    BillingServer::tool(GetQuote::class, ['quote_id' => $quote->id])->assertHasNoErrors();
});

it('limits send, accept, decline and void to owners and managers', function () {
    $member = User::factory()->create();
    $manager = User::factory()->create();
    $this->account->users()->attach($member, ['role' => AccountRole::Member->value]);
    $this->account->users()->attach($manager, ['role' => AccountRole::Manager->value]);
    $quote = Quote::factory()->forProject($this->project)->sent()->create();

    Sanctum::actingAs($member, ['*']);

    foreach ([SendQuote::class, AcceptQuote::class, DeclineQuote::class, VoidQuote::class] as $tool) {
        BillingServer::tool($tool, ['quote_id' => $quote->id, 'account_id' => $this->account->id])
            ->assertHasErrors(['owner or manager']);
    }

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);

    Sanctum::actingAs($manager, ['*']);
    BillingServer::tool(DeclineQuote::class, ['quote_id' => $quote->id, 'account_id' => $this->account->id])
        ->assertHasNoErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Declined);
});
