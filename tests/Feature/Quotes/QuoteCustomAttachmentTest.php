<?php

use App\Enums\AccountRole;
use App\Enums\EmailTemplateType;
use App\Enums\QuoteStatus;
use App\Filament\App\Resources\Quotes\Pages\CreateQuote;
use App\Filament\App\Resources\Quotes\Pages\EditQuote;
use App\Filament\App\Resources\Quotes\Pages\ViewQuote;
use App\Mail\QuoteMailSent;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Render the mailable (which builds its attachments) so attachment assertions can run.
 */
function renderAttachments(QuoteMailSent $mail): QuoteMailSent
{
    $mail->assertSeeInHtml('');

    return $mail;
}

/**
 * A draft quote whose account has a quote template, optionally with the custom attachment flag and a stored upload.
 *
 * @return array<string, mixed>
 */
function attachmentScenario(bool $flag, bool $withUpload): array
{
    $scenario = makeQuoteScenario(status: QuoteStatus::Draft);

    EmailTemplate::factory()->create([
        'account_id' => $scenario['account']->id,
        'type' => EmailTemplateType::QUOTE_REQUEST,
        'metadata' => ['features' => ['custom_attachment' => $flag]],
    ]);

    if ($withUpload) {
        Storage::disk('local')->put('quote-attachments/mine.pdf', '%PDF-1.4 uploaded');
        $scenario['quote']->update(['attachment_path' => 'quote-attachments/mine.pdf']);
    }

    $scenario['quote'] = $scenario['quote']->fresh();

    return $scenario;
}

it('hides the upload field when the flag is off and shows it when on', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: false, withUpload: false);
    bindFilamentTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->assertFormFieldHidden('attachment_path');

    $account->emailTemplates()->update(['metadata' => ['features' => ['custom_attachment' => true]]]);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->assertFormFieldVisible('attachment_path');

    Livewire::test(CreateQuote::class)
        ->assertFormFieldVisible('attachment_path');
});

it('hides the upload field when the account has no quote template', function () {
    ['owner' => $owner, 'account' => $account] = makeQuoteScenario(status: QuoteStatus::Draft);
    bindFilamentTenant($owner, $account);

    Livewire::test(CreateQuote::class)->assertFormFieldHidden('attachment_path');
});

it('stores an uploaded pdf on the private disk and saves its path on the quote', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: true, withUpload: false);
    bindFilamentTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['attachment_path' => UploadedFile::fake()->create('terms.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = $quote->fresh()->attachment_path;

    expect($path)->toStartWith('quote-attachments/');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-pdf upload and files over 10MB', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: true, withUpload: false);
    bindFilamentTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['attachment_path' => UploadedFile::fake()->image('logo.png')])
        ->call('save')
        ->assertHasFormErrors(['attachment_path']);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->fillForm(['attachment_path' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['attachment_path']);

    expect($quote->fresh()->attachment_path)->toBeNull();
});

it('does not touch a stored upload while the field is hidden', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: false, withUpload: true);
    bindFilamentTenant($owner, $account);

    Livewire::test(EditQuote::class, ['record' => $quote->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($quote->fresh()->attachment_path)->toBe('quote-attachments/mine.pdf');
    Storage::disk('local')->assertExists('quote-attachments/mine.pdf');
});

it('shows the attachment name on the quote view page', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->assertSee('mine.pdf');
});

it('deletes the stored file when the quote is deleted', function () {
    ['quote' => $quote] = attachmentScenario(flag: true, withUpload: true);

    $quote->delete();

    Storage::disk('local')->assertMissing('quote-attachments/mine.pdf');
});

it('deletes the previous file when the attachment is replaced or cleared', function () {
    ['quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    Storage::disk('local')->put('quote-attachments/other.pdf', '%PDF-1.4 other');

    $quote->update(['attachment_path' => 'quote-attachments/other.pdf']);

    Storage::disk('local')->assertMissing('quote-attachments/mine.pdf');
    Storage::disk('local')->assertExists('quote-attachments/other.pdf');

    $quote->update(['attachment_path' => null]);

    Storage::disk('local')->assertMissing('quote-attachments/other.pdf');
});

it('attaches the uploaded pdf when the flag is on and a file is stored', function () {
    ['quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    $mail = renderAttachments(new QuoteMailSent($quote));

    expect($mail->usesCustomAttachment())->toBeTrue();

    $mail->assertHasAttachedData('%PDF-1.4 uploaded', "Quote-{$quote->number}.pdf", ['mime' => 'application/pdf']);
    Storage::disk('local')->assertMissing("Quote-{$quote->number}.pdf");
});

it('falls back to the generated pdf when the flag is on but nothing is uploaded', function () {
    ['quote' => $quote] = attachmentScenario(flag: true, withUpload: false);
    $mail = renderAttachments(new QuoteMailSent($quote));

    expect($mail->usesCustomAttachment())->toBeFalse();

    $mail->assertHasAttachedData(Storage::disk('local')->get("Quote-{$quote->number}.pdf"), "Quote-{$quote->number}.pdf", ['mime' => 'application/pdf']);
});

it('falls back to the generated pdf when the stored file is missing from disk', function () {
    ['quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    Storage::disk('local')->delete('quote-attachments/mine.pdf');

    expect((new QuoteMailSent($quote->fresh()))->usesCustomAttachment())->toBeFalse();
});

it('ignores a stored upload when the flag is off', function () {
    ['quote' => $quote] = attachmentScenario(flag: false, withUpload: true);
    $mail = renderAttachments(new QuoteMailSent($quote));

    expect($mail->usesCustomAttachment())->toBeFalse();

    $mail->assertHasAttachedData(Storage::disk('local')->get("Quote-{$quote->number}.pdf"), "Quote-{$quote->number}.pdf", ['mime' => 'application/pdf']);
    expect($mail->hasAttachedData('%PDF-1.4 uploaded', "Quote-{$quote->number}.pdf", ['mime' => 'application/pdf']))->toBeFalse();
});

it('queues the email with the uploaded pdf when the quote is sent', function () {
    Mail::fake();
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->callAction('send');

    Mail::assertQueued(QuoteMailSent::class, fn (QuoteMailSent $mail): bool => renderAttachments($mail)->hasAttachedData(
        '%PDF-1.4 uploaded',
        "Quote-{$quote->number}.pdf",
        ['mime' => 'application/pdf'],
    ));
});

it('streams the uploaded pdf from the preview route when it applies', function () {
    ['quote' => $quote, 'owner' => $owner] = attachmentScenario(flag: true, withUpload: true);

    $response = $this->actingAs($owner)->get(route('quotes.preview', $quote));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->streamedContent())->toBe('%PDF-1.4 uploaded');
});

it('streams the generated pdf from the preview route when the flag is off', function () {
    ['quote' => $quote, 'owner' => $owner] = attachmentScenario(flag: false, withUpload: true);

    $response = $this->actingAs($owner)->get(route('quotes.preview', $quote));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->getContent())->not->toBe('%PDF-1.4 uploaded');
});

it('labels the preview modal when the uploaded pdf is used', function () {
    ['owner' => $owner, 'account' => $account, 'quote' => $quote] = attachmentScenario(flag: true, withUpload: true);
    bindFilamentTenant($owner, $account);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('preview')
        ->assertMountedActionModalSee('Using your uploaded PDF');

    $quote->update(['attachment_path' => null]);

    Livewire::test(ViewQuote::class, ['record' => $quote->id])
        ->mountAction('preview')
        ->assertMountedActionModalDontSee('Using your uploaded PDF');
});

it('still forbids the uploaded pdf to other accounts and members', function () {
    ['quote' => $quote, 'account' => $account] = attachmentScenario(flag: true, withUpload: true);

    $member = User::factory()->create();
    $account->users()->syncWithoutDetaching([$member->id => ['role' => AccountRole::Member->value]]);

    $this->actingAs(User::factory()->create())->get(route('quotes.preview', $quote))->assertForbidden();
    $this->actingAs($member)->get(route('quotes.preview', $quote))->assertForbidden();
});
