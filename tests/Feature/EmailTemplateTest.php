<?php

use App\Enums\EmailTemplateType;
use App\Filament\App\Pages\Tenancy\RegisterAccount;
use App\Models\Account;
use App\Models\EmailTemplate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates invoice and quote email templates when registering an account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user);
    Filament::setCurrentPanel('app');

    Livewire::test(RegisterAccount::class)
        ->fillForm([
            'name' => 'Acme Studio',
            'email' => 'billing@acme.test',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $account = Account::where('name', 'Acme Studio')->firstOrFail();

    expect($account->emailTemplates()->where('type', EmailTemplateType::INVOICE_REQUEST)->exists())->toBeTrue()
        ->and($account->emailTemplates()->where('type', EmailTemplateType::QUOTE_REQUEST)->exists())->toBeTrue();
});

it('seeds invoice and quote email templates for the seeded account', function () {
    $this->seed();

    $account = Account::where('name', 'Freelance Studio')->firstOrFail();

    expect($account->emailTemplates()->where('type', EmailTemplateType::INVOICE_REQUEST)->exists())->toBeTrue()
        ->and($account->emailTemplates()->where('type', EmailTemplateType::QUOTE_REQUEST)->exists())->toBeTrue();
});

it('backfills quote email templates for existing accounts via migration', function () {
    $accountWithoutQuoteTemplate = Account::factory()->create();
    $accountWithQuoteTemplate = Account::factory()->create();

    EmailTemplate::create([
        'account_id' => $accountWithQuoteTemplate->id,
        'type' => EmailTemplateType::QUOTE_REQUEST,
        'subject' => 'Existing Quote',
        'body' => 'Existing body',
    ]);

    expect(EmailTemplate::where('type', EmailTemplateType::QUOTE_REQUEST)->count())->toBe(1);

    $migration = include database_path('migrations/2026_08_16_102139_add_quote_request_email_template_to_existing_accounts.php');
    $migration->up();

    $quoteTemplates = EmailTemplate::where('type', EmailTemplateType::QUOTE_REQUEST)->get();

    expect($quoteTemplates->count())->toBe(2)
        ->and($quoteTemplates->where('account_id', $accountWithQuoteTemplate->id)->first()->body)->toBe('Existing body')
        ->and($quoteTemplates->where('account_id', $accountWithoutQuoteTemplate->id)->first()->body)
        ->toBe(config('email-templates.defaults.quote.body'));

    $migration->down();

    expect(EmailTemplate::where('type', EmailTemplateType::QUOTE_REQUEST)->count())->toBe(0);
});
