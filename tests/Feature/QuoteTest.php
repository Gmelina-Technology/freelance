<?php

use App\Enums\AccountRole;
use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Pages\EditQuote;
use App\Filament\App\Resources\Quotes\Pages\ViewQuote;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Mail\QuoteMailSent;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuoteService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function quoteAccountWithOwner(): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();

    return [$account, $owner];
}

function quoteForAccount(Account $account, array $attributes = []): Quote
{
    return Quote::factory()->for($account)->create($attributes);
}

function setQuotePanelTenant(User $user, Account $account): void
{
    Livewire::actingAs($user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($account);
}

it('creates a draft quote with line items via the factory', function () {
    [$account] = quoteAccountWithOwner();

    $quote = quoteForAccount($account);
    $item = QuoteItem::factory()->for($quote)->create([
        'quantity' => 3,
        'unit_price' => 50,
    ]);

    expect($quote->account_id)->toBe($account->id)
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->items()->count())->toBe(1)
        ->and((float) $item->refresh()->amount)->toBe(150.0);
});

it('generates a unique sequential quote number per account and month', function () {
    [$account] = quoteAccountWithOwner();

    quoteForAccount($account);

    $number = QuoteService::generateQuoteNumber($account->id);

    expect($number)->toMatch('/^Q\d{3}-\d{6}-\d{3}$/')
        ->and($number)->toBe(sprintf('Q%03d-%s-%03d', $account->id, now()->format('Ym'), 2));
});

it('sends a quote email to the client and marks the quote as sent', function () {
    Mail::fake();

    [$account, $owner] = quoteAccountWithOwner();
    $client = Client::factory()->for($account)->create([
        'email' => 'client@example.com',
    ]);
    $quote = quoteForAccount($account, [
        'client_id' => $client->id,
    ]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->callAction('send')
        ->assertNotified();

    Mail::assertQueued(QuoteMailSent::class, function (QuoteMailSent $mail) use ($client, $quote) {
        return $mail->hasTo($client->email)
            && $mail->quote->is($quote)
            && str_contains($mail->envelope()->subject, $quote->number);
    });

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('shows the send action only for draft quotes', function () {
    [$account, $owner] = quoteAccountWithOwner();

    $draft = quoteForAccount($account);
    $sent = quoteForAccount($account, ['status' => QuoteStatus::Sent]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $draft->id])
        ->assertActionVisible('send');

    Livewire::test(ViewQuote::class, ['record' => $sent->id])
        ->assertActionHidden('send');
});

it('keeps the quote as a draft when the email fails', function () {
    [$account] = quoteAccountWithOwner();
    $client = Client::factory()->for($account)->create([
        'email' => 'client@example.com',
    ]);
    $quote = quoteForAccount($account, [
        'client_id' => $client->id,
    ]);

    $mailable = new QuoteMailSent($quote);
    $mailable->failed(new RuntimeException('SMTP down'));

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('restricts the quotes resource from tenant members', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $member = User::factory()->create();
    $account->users()->syncWithoutDetaching([
        $member->id => ['role' => AccountRole::Member->value],
    ]);

    setQuotePanelTenant($owner, $account);
    expect(QuoteResource::canAccess())->toBeTrue();

    setQuotePanelTenant($member, $account);
    expect(QuoteResource::canAccess())->toBeFalse();
});

it('accepts a sent quote and marks it as accepted', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $quote = quoteForAccount($account, ['status' => QuoteStatus::Sent]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->callAction('acceptQuote')
        ->assertNotified();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('declines a sent quote and marks it as declined', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $quote = quoteForAccount($account, ['status' => QuoteStatus::Sent]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->callAction('declineQuote')
        ->assertNotified();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Declined);
});

it('shows the accept and decline actions only for sent quotes', function () {
    [$account, $owner] = quoteAccountWithOwner();

    $sent = quoteForAccount($account, ['status' => QuoteStatus::Sent]);
    $draft = quoteForAccount($account, ['status' => QuoteStatus::Draft]);
    $accepted = quoteForAccount($account, ['status' => QuoteStatus::Accepted]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $sent->id])
        ->assertActionVisible('acceptQuote')
        ->assertActionVisible('declineQuote');

    Livewire::test(ViewQuote::class, ['record' => $draft->id])
        ->assertActionHidden('acceptQuote')
        ->assertActionHidden('declineQuote');

    Livewire::test(ViewQuote::class, ['record' => $accepted->id])
        ->assertActionHidden('acceptQuote')
        ->assertActionHidden('declineQuote');
});

it('resets a declined quote to draft when it is edited', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $client = Client::factory()->for($account)->create();
    $project = Project::factory()->for($account)->for($client, 'client')->create();
    $quote = quoteForAccount($account, [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => QuoteStatus::Declined,
    ]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['notes' => 'Revised after decline'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft)
        ->and($quote->fresh()->notes)->toBe('Revised after decline');
});

it('keeps the status of a non-declined quote when it is edited', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $client = Client::factory()->for($account)->create();
    $project = Project::factory()->for($account)->for($client, 'client')->create();
    $quote = quoteForAccount($account, [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => QuoteStatus::Expired,
    ]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['notes' => 'Updated notes'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Expired)
        ->and($quote->fresh()->notes)->toBe('Updated notes');
});

it('prevents editing quotes that are sent or converted', function () {
    [$account] = quoteAccountWithOwner();

    $sent = quoteForAccount($account, ['status' => QuoteStatus::Sent]);
    $converted = quoteForAccount($account, ['status' => QuoteStatus::Converted]);
    $draft = quoteForAccount($account, ['status' => QuoteStatus::Draft]);
    $declined = quoteForAccount($account, ['status' => QuoteStatus::Declined]);

    expect(QuoteResource::canEdit($sent))->toBeFalse()
        ->and(QuoteResource::canEdit($converted))->toBeFalse()
        ->and(QuoteResource::canEdit($draft))->toBeTrue()
        ->and(QuoteResource::canEdit($declined))->toBeTrue();
});

it('hides the edit action for sent and converted quotes on the view page', function () {
    [$account, $owner] = quoteAccountWithOwner();

    $sent = quoteForAccount($account, ['status' => QuoteStatus::Sent]);
    $converted = quoteForAccount($account, ['status' => QuoteStatus::Converted]);
    $declined = quoteForAccount($account, ['status' => QuoteStatus::Declined]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $sent->id])
        ->assertActionHidden('edit');

    Livewire::test(ViewQuote::class, ['record' => $converted->id])
        ->assertActionHidden('edit');

    Livewire::test(ViewQuote::class, ['record' => $declined->id])
        ->assertActionVisible('edit');
});

it('loads and preserves line item amounts when editing a quote', function () {
    [$account, $owner] = quoteAccountWithOwner();
    $client = Client::factory()->for($account)->create();
    $project = Project::factory()->for($account)->for($client, 'client')->create();
    $unit = Unit::factory()->for($account)->create(['name' => 'Hour']);
    $task = Task::factory()->for($account)->for($client, 'client')->for($project, 'project')->create();
    $quote = quoteForAccount($account, [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => QuoteStatus::Draft,
    ]);
    QuoteItem::factory()->for($quote)->create([
        'task_id' => $task->id,
        'unit_id' => $unit->id,
        'quantity' => 3,
        'unit_price' => 50,
    ]);

    setQuotePanelTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['notes' => 'Updated with items'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($quote->fresh()->notes)->toBe('Updated with items')
        ->and($quote->fresh()->items()->first()->amount)->toBe('150.00');
});
