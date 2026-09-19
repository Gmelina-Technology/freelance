<?php

use App\Enums\AccountRole;
use App\Enums\EmailTemplateType;
use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Pages\ViewQuote;
use App\Mail\QuoteMailSent;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeQuoteTemplate(int $accountId, ?string $body = null): EmailTemplate
{
    return EmailTemplate::create([
        'account_id' => $accountId,
        'type' => EmailTemplateType::QUOTE_REQUEST,
        'subject' => 'Quote',
        'body' => $body ?? config('email-templates.defaults.quote.body'),
    ]);
}

it('shows the preview action on draft quotes only', function () {
    ['quote' => $draft, 'owner' => $owner, 'account' => $account] = makeQuoteScenario(status: QuoteStatus::Draft);
    ['quote' => $sent] = makeQuoteScenario(status: QuoteStatus::Sent);
    $sent->update(['account_id' => $account->id]);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $draft->id])->assertActionVisible('preview');
    Livewire::test(ViewQuote::class, ['record' => $sent->id])->assertActionHidden('preview');
});

it('renders the recipient, subject and merge tags in the preview modal', function () {
    ['quote' => $quote, 'owner' => $owner, 'account' => $account, 'client' => $client] = makeQuoteScenario(status: QuoteStatus::Draft);
    $client->update(['name' => 'Acme Studio']);
    makeQuoteTemplate($account->id);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('preview')
        ->assertMountedActionModalSee($client->email)
        ->assertMountedActionModalSee("Quote #{$quote->number}")
        ->assertMountedActionModalSee(['Acme Studio', $quote->number, 'Quote Amount:'])
        ->assertMountedActionModalSee(route('quotes.preview', $quote));
});

it('warns when the client has no email or the account has no template', function () {
    ['quote' => $quote, 'owner' => $owner, 'account' => $account, 'client' => $client] = makeQuoteScenario(status: QuoteStatus::Draft);
    $client->update(['email' => null]);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('preview')
        ->assertMountedActionModalSee('no email address')
        ->assertMountedActionModalSee('No quote email template');
});

it('previews without sending mail or changing status', function () {
    Mail::fake();
    ['quote' => $quote, 'owner' => $owner, 'account' => $account] = makeQuoteScenario(status: QuoteStatus::Draft);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('preview')
        ->unmountAction();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('shows the preview inside the send confirmation modal', function () {
    ['quote' => $quote, 'owner' => $owner, 'account' => $account, 'client' => $client] = makeQuoteScenario(status: QuoteStatus::Draft);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('send')
        ->assertMountedActionModalSee($client->email)
        ->assertMountedActionModalSee("Quote #{$quote->number}");
});

it('streams the quote pdf to the account owner', function () {
    ['quote' => $quote, 'owner' => $owner] = makeQuoteScenario(status: QuoteStatus::Draft);

    $response = $this->actingAs($owner)->get(route('quotes.preview', $quote));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('forbids the quote pdf to other accounts and members, and redirects guests', function () {
    ['quote' => $quote, 'account' => $account] = makeQuoteScenario(status: QuoteStatus::Draft);

    $member = User::factory()->create();
    $account->users()->syncWithoutDetaching([$member->id => ['role' => AccountRole::Member->value]]);
    $manager = User::factory()->create();
    $account->users()->syncWithoutDetaching([$manager->id => ['role' => AccountRole::Manager->value]]);

    $this->actingAs(User::factory()->create())->get(route('quotes.preview', $quote))->assertForbidden();
    $this->actingAs($member)->get(route('quotes.preview', $quote))->assertForbidden();
    $this->actingAs($manager)->get(route('quotes.preview', $quote))->assertOk();

    auth()->logout();
    $this->get(route('quotes.preview', $quote))->assertRedirect();
});

it('puts the quote line title, description and category in the pdf', function () {
    ['quote' => $quote, 'account' => $account] = makeQuoteScenario([
        ['title' => 'Logo design', 'description' => 'Three concepts', 'quantity' => 1, 'unit_price' => 100],
    ], QuoteStatus::Draft);
    $category = Category::factory()->create(['account_id' => $account->id, 'name' => 'Branding']);
    $quote->items()->first()->update(['category_id' => $category->id]);

    $pdf = (new QuoteMailSent($quote->fresh()))->buildPdf();
    $pdf->render();
    $html = $pdf->toHtml()->render();

    expect($html)->toContain('Logo design')->toContain('Three concepts')->toContain('Branding');
});
