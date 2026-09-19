<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Exceptions\NoBillableTasksException;
use App\Filament\App\Resources\Projects\Pages\ListProjects;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Task;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * @return array{scenario: array<string, mixed>, tasks: Collection<int, Task>}
 */
function acceptedProject(array $lines = []): array
{
    $scenario = makeQuoteScenario($lines);

    app(QuoteService::class)->accept($scenario['quote']);

    return ['scenario' => $scenario, 'tasks' => Task::query()->orderBy('id')->get()];
}

it('bills only completed tasks', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    $tasks[0]->update(['status' => 'completed']);
    $tasks[1]->update(['status' => 'in_progress']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->task_id)->toBe($tasks[0]->id);
});

it('copies quantity, price and unit from the quote line and totals the invoice', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    Task::query()->update(['status' => 'completed']);
    // Tasks carry no price columns, so a changed quote line is the only source of truth.
    $s['quote']->items()->first()->update(['unit_price' => 120]);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    $first = $invoice->items()->orderBy('id')->first();

    expect($first->unit_id)->toBe($s['unit']->id)
        ->and($first->quantity)->toEqual('2.00')
        ->and($first->unit_price)->toEqual('120.00')
        ->and($first->amount)->toEqual('240.00')
        ->and($invoice->amount)->toEqual('890.00')
        ->and($invoice->items()->get()->sum('amount'))->toEqual(890);
});

it('creates a draft invoice with the project account, client and project', function () {
    ['scenario' => $s] = acceptedProject();
    Task::query()->update(['status' => 'completed']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->account_id)->toBe($s['account']->id)
        ->and($invoice->client_id)->toBe($s['client']->id)
        ->and($invoice->project_id)->toBe($s['project']->id)
        ->and($invoice->task_id)->toBeNull()
        ->and($invoice->number)->toMatch('/^\d{3}-\d{6}-\d{3}$/');
});

it('throws and creates nothing when no task is billable', function () {
    ['scenario' => $s] = acceptedProject();

    expect(fn () => app(InvoiceService::class)->generateFromCompletedTasks($s['project']))
        ->toThrow(NoBillableTasksException::class);

    expect(Invoice::count())->toBe(0);
});

it('supports progress billing', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    $tasks[0]->update(['status' => 'completed']);
    $tasks[1]->update(['status' => 'completed']);

    $first = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    $tasks[2]->update(['status' => 'completed']);

    $second = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($first->items)->toHaveCount(2)
        ->and($second->items)->toHaveCount(1)
        ->and($second->items->first()->task_id)->toBe($tasks[2]->id);
});

it('never bills a task without a quote line', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    Task::query()->update(['status' => 'completed']);
    Task::factory()->completed()->create([
        'account_id' => $s['account']->id,
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'quote_item_id' => null,
    ]);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($invoice->items)->toHaveCount(3);
});

it('never bills a task whose quote is not accepted', function () {
    $s = makeQuoteScenario();
    $item = $s['quote']->items()->first();
    Task::factory()->completed()->forQuoteItem($item)->create();

    expect($s['quote']->fresh()->status)->toBe(QuoteStatus::Sent);
    expect(fn () => app(InvoiceService::class)->generateFromCompletedTasks($s['project']))
        ->toThrow(NoBillableTasksException::class);
});

it('keeps fractional quantities from quote to invoice', function () {
    ['scenario' => $s] = acceptedProject();
    Task::query()->update(['status' => 'completed']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);
    $build = $invoice->items()->where('quantity', '>', 7)->firstOrFail();

    expect($build->quantity)->toEqual('7.50')
        ->and($build->amount)->toEqual('600.00');
});

it('draws one invoice from several accepted quotes on the same project', function () {
    ['scenario' => $s] = acceptedProject();
    $other = Quote::factory()->forProject($s['project'])->create(['status' => QuoteStatus::Sent]);
    QuoteItem::factory()->for($other)->create(['title' => 'Extra', 'quantity' => 1, 'unit_price' => 10]);
    app(QuoteService::class)->accept($other);
    Task::query()->update(['status' => 'completed']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($invoice->items)->toHaveCount(4)
        ->and($invoice->items->pluck('quote_item_id')->unique())->toHaveCount(4);
});

it('bills only the selected tasks and leaves the rest billable', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    Task::query()->update(['status' => 'completed']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project'], [$tasks[0]->id, $tasks[1]->id]);

    expect($invoice->items()->pluck('task_id')->all())->toEqual([$tasks[0]->id, $tasks[1]->id])
        ->and($invoice->amount)->toEqual('800.00')
        ->and($tasks[2]->fresh()->billing_status->value)->toBe('billable');

    $second = app(InvoiceService::class)->generateFromCompletedTasks($s['project']);

    expect($second->items()->pluck('task_id')->all())->toEqual([$tasks[2]->id]);
});

it('ignores selected tasks that are not eligible', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    $tasks[0]->update(['status' => 'completed']);
    $tasks[1]->update(['status' => 'in_progress']);

    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($s['project'], [$tasks[0]->id, $tasks[1]->id]);

    expect($invoice->items)->toHaveCount(1);

    $before = Invoice::count();

    expect(fn () => app(InvoiceService::class)->generateFromCompletedTasks($s['project'], [$tasks[1]->id, $tasks[0]->id]))
        ->toThrow(NoBillableTasksException::class)
        ->and(Invoice::count())->toBe($before);
});

it('generates an invoice for the tasks picked in the action modal', function () {
    ['scenario' => $s, 'tasks' => $tasks] = acceptedProject();
    Task::query()->update(['status' => 'completed']);
    bindFilamentTenant($s['owner'], $s['account']);

    Livewire\Livewire::test(ListProjects::class)
        ->mountAction(TestAction::make('generateInvoice')->table($s['project']))
        // fillForm() merges into the preselected default by index; setting the state replaces it, like unticking boxes in the UI.
        ->set('mountedActions.0.data.task_ids', [$tasks[1]->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Invoice::query()->firstOrFail()->items()->pluck('task_id')->all())->toEqual([$tasks[1]->id])
        ->and($tasks[0]->fresh()->billing_status->value)->toBe('billable');
});

it('requires at least one task to be selected', function () {
    ['scenario' => $s] = acceptedProject();
    Task::query()->update(['status' => 'completed']);
    bindFilamentTenant($s['owner'], $s['account']);

    Livewire\Livewire::test(ListProjects::class)
        ->callAction(TestAction::make('generateInvoice')->table($s['project']), ['task_ids' => []])
        ->assertHasActionErrors(['task_ids' => 'required']);

    expect(Invoice::count())->toBe(0);
});
