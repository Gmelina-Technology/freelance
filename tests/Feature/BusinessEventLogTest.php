<?php

use App\Enums\InvoiceStatus;
use App\Filament\App\Pages\ApiTokens;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/**
 * Route the business channel to a collector.
 *
 * @return ArrayObject<int, array{0: string, 1: array<string, mixed>}>
 */
function captureBusinessEvents(): ArrayObject
{
    $events = new ArrayObject;
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use ($events): void {
        $events[] = [$message, $context];
    });

    Log::partialMock()->shouldReceive('channel')->with('business')->andReturn($channel);

    return $events;
}

/**
 * @param  ArrayObject<int, array{0: string, 1: array<string, mixed>}>  $events
 * @return array<int, array<string, mixed>>
 */
function eventsNamed(ArrayObject $events, string $name): array
{
    return collect($events->getArrayCopy())->filter(fn (array $event): bool => $event[0] === $name)->pluck(1)->values()->all();
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('logs invoice.sent once with the account and user', function () {
    Mail::fake();
    $events = captureBusinessEvents();
    $client = Client::factory()->for($this->account)->create();
    $invoice = Invoice::factory()->for($this->account)->for($client)->create(['status' => InvoiceStatus::Draft]);
    $this->actingAs($this->owner);

    app(InvoiceService::class)->send($invoice);

    expect(eventsNamed($events, 'invoice.sent'))->toHaveCount(1)
        ->and(eventsNamed($events, 'invoice.sent')[0])->toMatchArray([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'invoice_id' => $invoice->id,
        ]);
});

it('logs invoice.paid once', function () {
    $events = captureBusinessEvents();
    $client = Client::factory()->for($this->account)->create();
    $invoice = Invoice::factory()->for($this->account)->for($client)->create(['status' => InvoiceStatus::Sent]);
    $this->actingAs($this->owner);

    app(InvoiceService::class)->markAsPaid($invoice);

    expect(eventsNamed($events, 'invoice.paid'))->toHaveCount(1)
        ->and(eventsNamed($events, 'invoice.paid')[0])->toMatchArray([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'invoice_id' => $invoice->id,
        ]);
});

it('logs quote.accepted once even when the quote is accepted again', function () {
    $events = captureBusinessEvents();
    $scenario = makeQuoteScenario();
    $this->actingAs($scenario['owner']);

    app(QuoteService::class)->accept($scenario['quote']);
    app(QuoteService::class)->accept($scenario['quote']);

    expect(eventsNamed($events, 'quote.accepted'))->toHaveCount(1)
        ->and(eventsNamed($events, 'quote.accepted')[0])->toMatchArray([
            'account_id' => $scenario['account']->id,
            'user_id' => $scenario['owner']->id,
            'quote_id' => $scenario['quote']->id,
            'tasks_created' => 3,
        ]);
});

it('logs api_token.created without the token, its name or any email address', function () {
    $events = captureBusinessEvents();
    $this->actingAs($this->owner);

    $token = $this->owner->createAccountToken($this->account, 'Secret laptop', ['tasks:write'], now()->addDays(30));

    $logged = eventsNamed($events, 'api_token.created');

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toMatchArray([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'token_id' => $token->accessToken->id,
            'abilities' => 'tasks:write',
        ]);

    $json = json_encode($logged);

    expect($json)->not->toContain($token->plainTextToken)
        ->and($json)->not->toContain('Secret laptop')
        ->and($json)->not->toContain('@');
});

it('logs api_token.revoked when a token is revoked from the page', function () {
    $events = captureBusinessEvents();
    $token = $this->owner->createAccountToken($this->account, 'Old')->accessToken;
    bindFilamentTenant($this->owner, $this->account);

    Livewire::test(ApiTokens::class)->callAction(TestAction::make('revoke')->table($token));

    $logged = eventsNamed($events, 'api_token.revoked');

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toMatchArray([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'token_id' => $token->id,
        ])
        ->and(json_encode($logged))->not->toContain('Old');
});

it('logs invitation.accepted with the accepting user for a logged-in invitee', function () {
    $events = captureBusinessEvents();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = Invitation::factory()->for($this->account)->create(['email' => 'invitee@example.com']);
    $this->actingAs($invitee);

    app(InvitationService::class)->acceptForUser($invitation, $invitee);

    $logged = eventsNamed($events, 'invitation.accepted');

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toMatchArray([
            'account_id' => $this->account->id,
            'user_id' => $invitee->id,
            'invitation_id' => $invitation->id,
            'role' => 'member',
            'member_user_id' => $invitee->id,
        ])
        ->and(json_encode($logged))->not->toContain('@');
});

it('logs invitation.accepted with no user for a guest who is a new invitee', function () {
    Notification::fake();
    $events = captureBusinessEvents();
    $invitation = Invitation::factory()->for($this->account)->create(['email' => 'brand.new@example.com']);

    $user = app(InvitationService::class)->acceptAsNewUser($invitation);

    $logged = eventsNamed($events, 'invitation.accepted');

    expect($logged)->toHaveCount(1)
        ->and($logged[0]['user_id'])->toBeNull()
        ->and($logged[0]['member_user_id'])->toBe($user->id);
});

it('does not log an invitation that was refused', function () {
    $events = captureBusinessEvents();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = Invitation::factory()->for($this->account)->expired()->create(['email' => 'invitee@example.com']);

    expect(fn () => app(InvitationService::class)->acceptForUser($invitation, $invitee))->toThrow(DomainException::class);

    expect(eventsNamed($events, 'invitation.accepted'))->toBeEmpty();
});
