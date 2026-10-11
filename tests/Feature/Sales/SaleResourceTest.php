<?php

use App\Enums\AccountRole;
use App\Filament\App\Resources\Sales\Actions\ConvertSaleAction;
use App\Filament\App\Resources\Sales\Actions\EditSaleConversionAction;
use App\Filament\App\Resources\Sales\Pages\ListSales;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));

    Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->withCurrency('EUR')->for($this->owner, 'owner')->create();

    $this->unconverted = Sale::factory()->for($this->account)->unconverted('USD')->create(['amount' => 1200]);
    $this->converted = Sale::factory()->for($this->account)->converted('EUR', 1050)->create(['amount' => 1050]);

    bindFilamentTenant($this->owner, $this->account);
});

it('shows the conversion status and the net received', function () {
    Livewire::test(ListSales::class)
        ->assertOk()
        ->assertSee(['Unconverted', 'Converted', '€1,050.00', 'USD'])
        ->assertDontSee('Set your account currency in Account Profile to convert sales.');
});

it('filters by conversion status', function () {
    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$this->unconverted, $this->converted])
        ->filterTable('converted', true)
        ->assertCanSeeTableRecords([$this->converted])
        ->assertCanNotSeeTableRecords([$this->unconverted])
        ->filterTable('converted', false)
        ->assertCanSeeTableRecords([$this->unconverted])
        ->assertCanNotSeeTableRecords([$this->converted]);
});

it('offers convert on unconverted rows and edit on converted rows', function () {
    Livewire::test(ListSales::class)
        ->assertActionVisible(TestAction::make('convert')->table($this->unconverted))
        ->assertActionHidden(TestAction::make('editConversion')->table($this->unconverted))
        ->assertActionHidden(TestAction::make('convert')->table($this->converted))
        ->assertActionVisible(TestAction::make('editConversion')->table($this->converted));
});

it('lets owners and managers convert and edit a conversion', function (?AccountRole $role) {
    $user = $role ? makeAccountUser($this->account, $role) : $this->owner;
    bindFilamentTenant($user, $this->account);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('convert')->table($this->unconverted), [
            'converted_amount' => '1100.00',
            'fee_amount' => '15.50',
            'conversion_note' => 'Bank transfer',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Sale converted')
        ->callAction(TestAction::make('editConversion')->table($this->converted), [
            'converted_amount' => '1000.00',
            'tax_amount' => '50.00',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Conversion updated');

    $converted = $this->unconverted->fresh();
    $edited = $this->converted->fresh();

    expect($converted->converted_amount)->toBe('1100.00')
        ->and($converted->gross_amount)->toBe('1115.50')
        ->and($converted->converted_currency_code)->toBe('EUR')
        ->and($converted->converted_by)->toBe($user->id)
        ->and($edited->converted_amount)->toBe('1000.00')
        ->and($edited->gross_amount)->toBe('1050.00')
        ->and($edited->converted_by)->toBe($user->id);
})->with([
    'owner' => [null],
    'manager' => [AccountRole::Manager],
]);

it('asks for the original currency when it is unknown', function () {
    $unknown = Sale::factory()->for($this->account)->unconverted()->create(['amount' => 300]);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('convert')->table($unknown), ['converted_amount' => '250.00'])
        ->assertHasFormErrors(['currency_code' => 'required']);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('convert')->table($unknown), ['converted_amount' => '250.00', 'currency_code' => 'USD'])
        ->assertHasNoFormErrors();

    expect($unknown->fresh()->currency_code)->toBe('USD')
        ->and($unknown->fresh()->isConverted())->toBeTrue();
});

it('rejects a net amount with more than two decimals', function () {
    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('convert')->table($this->unconverted), ['converted_amount' => '10.123'])
        ->assertHasFormErrors(['converted_amount']);

    expect($this->unconverted->fresh()->isConverted())->toBeFalse();
});

it('prefills the original amount when the sale is already in the account currency', function () {
    $sameCurrency = Sale::factory()->for($this->account)->unconverted('EUR')->create(['amount' => 640]);

    Livewire::test(ListSales::class)
        ->mountAction(TestAction::make('convert')->table($sameCurrency))
        ->assertSchemaStateSet(['converted_amount' => '640.00']);
});

it('disables convert and explains why when the account has no currency', function () {
    $this->account->update(['currency_code' => null]);

    Livewire::test(ListSales::class)
        ->assertSee('Set your account currency in Account Profile to convert sales.')
        ->assertActionDisabled(TestAction::make('convert')->table($this->unconverted))
        ->assertActionDisabled(TestAction::make('editConversion')->table($this->converted));
});

it('disables editing a conversion made into a previous account currency', function () {
    $this->account->update(['currency_code' => 'USD']);

    Livewire::test(ListSales::class)
        ->assertActionDisabled(TestAction::make('editConversion')->table($this->converted));
});

it('keeps members out of the sales list and its actions', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    bindFilamentTenant($member, $this->account);

    Livewire::test(ListSales::class)->assertForbidden();

    expect(ConvertSaleAction::handle()->record($this->unconverted)->isAuthorized())->toBeFalse()
        ->and(EditSaleConversionAction::handle()->record($this->converted)->isAuthorized())->toBeFalse();
});

it('cannot convert a sale from another account', function () {
    $otherAccount = Account::factory()->withCurrency('EUR')->create();

    // With a tenant bound, Filament would move a newly created sale into the current tenant.
    $otherSale = Sale::withoutEvents(fn () => Sale::factory()->for($otherAccount)->unconverted('USD')->create());

    try {
        Livewire::test(ListSales::class)
            ->callAction(TestAction::make('convert')->table($otherSale), ['converted_amount' => '10.00']);
    } catch (Throwable) {
        // Filament refuses records outside the tenant's sales query.
    }

    expect($otherSale->fresh()->account_id)->toBe($otherAccount->id)
        ->and($otherSale->fresh()->isConverted())->toBeFalse();
});
