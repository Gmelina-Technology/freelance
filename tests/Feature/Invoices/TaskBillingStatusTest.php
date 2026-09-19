<?php

use App\Enums\InvoiceStatus;
use App\Enums\TaskBillingStatus;
use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\App\Resources\Invoices\Pages\EditInvoice;
use App\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\Task;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * An accepted quote whose three tasks are all completed and billable.
 *
 * @return array<string, mixed>
 */
function completedProject(): array
{
    $scenario = makeQuoteScenario();
    app(QuoteService::class)->accept($scenario['quote']);
    Task::query()->update(['status' => 'completed']);

    return $scenario;
}

function billingStatuses(): array
{
    return Task::query()->orderBy('id')->pluck('billing_status')->map->value->unique()->values()->all();
}

it('moves tasks from billable to pending payment when an invoice is generated', function () {
    $s = completedProject();

    app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect(billingStatuses())->toBe(['pending_payment']);
});

it('refuses a second generate immediately after the first', function () {
    $s = completedProject();

    app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect(fn () => app(InvoiceService::class)->generateFromCompletedTasks($s['project']))
        ->toThrow(NoBillableTasksException::class);
    expect(Invoice::count())->toBe(1);
});

it('settles tasks to paid when the invoice is marked paid and never bills them again', function () {
    $s = completedProject();
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    app(InvoiceService::class)->markAsPaid($invoice);

    expect(billingStatuses())->toBe(['paid'])
        ->and(Sale::count())->toBe(1)
        ->and(fn () => app(InvoiceService::class)->generateFromCompletedTasks($s['project']))
        ->toThrow(NoBillableTasksException::class);
});

it('returns tasks to billable when the invoice is voided, so they can be billed again', function () {
    $s = completedProject();
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    bindFilamentTenant($s['owner'], $s['account']);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->callAction('void')
        ->assertNotified();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and(billingStatuses())->toBe(['billable']);

    $again = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($again->items)->toHaveCount(3);
});

it('returns tasks to billable when a draft invoice is deleted', function () {
    $s = completedProject();
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    $invoice->delete();

    expect(billingStatuses())->toBe(['billable'])
        ->and(Invoice::count())->toBe(0);
});

it('leaves paid tasks paid when releaseTasks is called', function () {
    $s = completedProject();
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);
    app(InvoiceService::class)->markAsPaid($invoice);

    app(InvoiceService::class)->releaseTasks($invoice);

    expect(billingStatuses())->toBe(['paid']);
});

it('claims tasks placed on a manually created invoice', function () {
    $s = makeQuoteScenario();
    app(QuoteService::class)->accept($s['quote']);
    $task = Task::query()->first();

    bindFilamentTenant($s['owner'], $s['account']);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'client_id' => $s['client']->id,
            'project_id' => $s['project']->id,
            'items' => [
                ['task_id' => $task->id, 'unit_id' => $s['unit']->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($task->fresh()->billing_status)->toBe(TaskBillingStatus::PendingPayment);
});

it('does not offer pending payment or paid tasks in the manual wizard', function (TaskBillingStatus $status) {
    $s = makeQuoteScenario();
    app(QuoteService::class)->accept($s['quote']);
    $task = Task::query()->first();
    $task->update(['billing_status' => $status]);

    bindFilamentTenant($s['owner'], $s['account']);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'client_id' => $s['client']->id,
            'project_id' => $s['project']->id,
            'items' => [
                ['task_id' => $task->id, 'unit_id' => $s['unit']->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Invoice::count())->toBe(0);
})->with([
    'pending payment' => TaskBillingStatus::PendingPayment,
    'paid' => TaskBillingStatus::Paid,
]);

it('re-syncs claims when a draft invoice is edited', function () {
    $s = completedProject();
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);
    $removed = $invoice->items()->orderBy('id')->first();

    bindFilamentTenant($s['owner'], $s['account']);

    $items = $invoice->items()->orderBy('id')->get()->skip(1)->mapWithKeys(fn ($item) => ["record-{$item->id}" => [
        'id' => $item->id,
        'task_id' => $item->task_id,
        'unit_id' => $item->unit_id,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
    ]])->all();

    Livewire::test(EditInvoice::class, ['record' => $invoice->id])
        ->set('data.items', $items)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Task::find($removed->task_id)->billing_status)->toBe(TaskBillingStatus::Billable)
        ->and(Task::where('billing_status', TaskBillingStatus::PendingPayment)->count())->toBe(2)
        ->and($invoice->fresh()->items)->toHaveCount(2);
});
