<?php

use App\Enums\EmailTemplateType;
use App\Filament\App\Clusters\Settings\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Models\Account;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function makeTemplatesForAccount(): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();

    $quote = EmailTemplate::factory()->create([
        'account_id' => $account->id,
        'type' => EmailTemplateType::QUOTE_REQUEST,
    ]);
    $invoice = EmailTemplate::factory()->create([
        'account_id' => $account->id,
        'type' => EmailTemplateType::INVOICE_REQUEST,
    ]);

    bindFilamentTenant($owner, $account);

    return compact('owner', 'account', 'quote', 'invoice');
}

it('reports every feature as disabled by default', function () {
    $template = EmailTemplate::factory()->create();

    expect($template->metadata)->toBeNull()
        ->and($template->featureEnabled('custom_attachment'))->toBeFalse()
        ->and($template->featureEnabled('unknown_flag'))->toBeFalse();
});

it('reads feature flags from the metadata', function () {
    $template = EmailTemplate::factory()->create([
        'metadata' => ['features' => ['custom_attachment' => true, 'other' => false]],
    ]);

    expect($template->featureEnabled('custom_attachment'))->toBeTrue()
        ->and($template->featureEnabled('other'))->toBeFalse();
});

it('casts metadata to an array and round-trips it', function () {
    $template = EmailTemplate::factory()->create([
        'metadata' => ['features' => ['custom_attachment' => true], 'note' => 'kept'],
    ]);

    $fresh = $template->fresh();

    expect($fresh->metadata)->toBe(['features' => ['custom_attachment' => true], 'note' => 'kept'])
        ->and($fresh->type)->toBe(EmailTemplateType::QUOTE_REQUEST);
});

it('declares the features each template type supports', function () {
    $quote = EmailTemplate::factory()->make(['type' => EmailTemplateType::QUOTE_REQUEST]);
    $invoice = EmailTemplate::factory()->make(['type' => EmailTemplateType::INVOICE_REQUEST]);

    expect(array_keys($quote->availableFeatures()))->toBe(['custom_attachment'])
        ->and($invoice->availableFeatures())->toBe([]);
});

it('persists the custom attachment flag from the quote template settings', function () {
    ['quote' => $quote] = makeTemplatesForAccount();

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $quote)
        ->fillForm(['metadata.features.custom_attachment' => true])
        ->callMountedTableAction()
        ->assertHasNoFormErrors();

    expect($quote->fresh()->featureEnabled('custom_attachment'))->toBeTrue();

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $quote)
        ->fillForm(['metadata.features.custom_attachment' => false])
        ->callMountedTableAction();

    expect($quote->fresh()->featureEnabled('custom_attachment'))->toBeFalse();
});

it('keeps unrelated metadata when saving the feature flags', function () {
    ['quote' => $quote] = makeTemplatesForAccount();
    $quote->update(['metadata' => ['note' => 'kept']]);

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $quote)
        ->fillForm(['metadata.features.custom_attachment' => true])
        ->callMountedTableAction();

    expect($quote->fresh()->metadata)->toBe(['note' => 'kept', 'features' => ['custom_attachment' => true]]);
});

it('shows no feature toggles for the invoice template', function () {
    ['invoice' => $invoice] = makeTemplatesForAccount();

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $invoice)
        ->assertMountedActionModalDontSee('Use custom attachment')
        ->callMountedTableAction()
        ->assertHasNoFormErrors();

    expect($invoice->fresh()->metadata)->toBeNull();
});

it('shows the feature toggle with its description for the quote template', function () {
    ['quote' => $quote] = makeTemplatesForAccount();

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $quote)
        ->assertMountedActionModalSee('Use custom attachment')
        ->assertMountedActionModalSee('instead of the generated quote PDF');
});

it('still saves subject and body without enabling any feature', function () {
    ['quote' => $quote] = makeTemplatesForAccount();

    Livewire::test(ManageEmailTemplates::class)
        ->mountTableAction('edit', $quote)
        ->fillForm(['subject' => 'New subject'])
        ->callMountedTableAction()
        ->assertHasNoFormErrors();

    expect($quote->fresh()->subject)->toBe('New subject')
        ->and($quote->fresh()->featureEnabled('custom_attachment'))->toBeFalse();
});

it('seeds templates with every feature flag off', function () {
    $this->seed();

    $templates = Account::where('name', 'Freelance Studio')->firstOrFail()->emailTemplates;

    expect($templates)->toHaveCount(2)
        ->and($templates->every(fn (EmailTemplate $template): bool => ! $template->featureEnabled('custom_attachment')))->toBeTrue();
});
