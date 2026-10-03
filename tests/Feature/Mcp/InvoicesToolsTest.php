<?php

use App\Enums\AccountRole;
use App\Enums\InvoiceStatus;
use App\Enums\TaskBillingStatus;
use App\Mail\InvoiceMailSent;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Invoices\CreateInvoice;
use App\Mcp\Tools\Invoices\GenerateInvoice;
use App\Mcp\Tools\Invoices\GetInvoice;
use App\Mcp\Tools\Invoices\GetInvoicePdf;
use App\Mcp\Tools\Invoices\ListBillableTasks;
use App\Mcp\Tools\Invoices\ListInvoices;
use App\Mcp\Tools\Invoices\MarkInvoicePaid;
use App\Mcp\Tools\Invoices\SendInvoice;
use App\Mcp\Tools\Invoices\VoidInvoice;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\Task;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * An accepted quote with three completed tasks, and the owner acting through a full-access token.
 *
 * @return array<string, mixed>
 */
function billableScenario(): array
{
    $scenario = makeQuoteScenario();
    app(QuoteService::class)->accept($scenario['quote']);
    Task::query()->update(['status' => 'completed']);
    actingAsToken($scenario['owner'], ['*']);

    return $scenario;
}

function generatedInvoice(array $scenario, InvoiceStatus $status = InvoiceStatus::Draft): Invoice
{
    $invoice = app(InvoiceService::class)->generateFromCompletedTasks($scenario['project']);
    $invoice->update(['status' => $status]);

    return $invoice;
}

function taskBillingStatuses(): array
{
    return Task::query()->pluck('billing_status')->map->value->unique()->values()->all();
}

it('lists invoices of the account filtered by status, client and project', function () {
    $s = billableScenario();
    $draft = generatedInvoice($s);
    $other = Invoice::factory()->create([
        'account_id' => $s['account']->id,
        'client_id' => $s['client']->id,
        'status' => InvoiceStatus::Sent,
        'number' => 'SENT-1',
    ]);

    BillingServer::tool(ListInvoices::class)
        ->assertOk()
        ->assertSee($draft->number)
        ->assertSee('SENT-1');

    BillingServer::tool(ListInvoices::class, ['status' => 'sent'])
        ->assertSee('SENT-1')
        ->assertDontSee($draft->number);

    BillingServer::tool(ListInvoices::class, ['project_id' => $s['project']->id])
        ->assertSee($draft->number)
        ->assertDontSee('SENT-1');

    BillingServer::tool(ListInvoices::class, ['client_id' => $other->client_id])
        ->assertSee('SENT-1');
});

it('rejects an unknown status filter', function () {
    billableScenario();

    BillingServer::tool(ListInvoices::class, ['status' => 'bogus'])->assertHasErrors(['Validation failed']);
});

it('does not list or show invoices of another account', function () {
    $s = billableScenario();
    $foreign = Invoice::factory()->create(['number' => 'FOREIGN-1']);

    BillingServer::tool(ListInvoices::class)->assertDontSee('FOREIGN-1');
    BillingServer::tool(GetInvoice::class, ['invoice_id' => $foreign->id])->assertHasErrors(['not found']);
    BillingServer::tool(GetInvoicePdf::class, ['invoice_id' => $foreign->id])->assertHasErrors(['not found']);
});

it('gets an invoice with its items', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s);

    BillingServer::tool(GetInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('"items"')
        ->assertSee('"amount":200');
});

it('lists the billable tasks of a project', function () {
    $s = billableScenario();

    BillingServer::tool(ListBillableTasks::class, ['project_id' => $s['project']->id])
        ->assertOk()
        ->assertSee('Design')
        ->assertSee('Build')
        ->assertSee('Launch');
});

it('refuses billable tasks of a project in another account', function () {
    billableScenario();
    $foreign = makeQuoteScenario();

    BillingServer::tool(ListBillableTasks::class, ['project_id' => $foreign['project']->id])
        ->assertHasErrors(['not found']);
});

it('generates an invoice from completed tasks and claims them', function () {
    $s = billableScenario();

    BillingServer::tool(GenerateInvoice::class, ['project_id' => $s['project']->id])
        ->assertOk()
        ->assertSee('"status":"draft"')
        ->assertSee('"amount":850');

    expect(Invoice::count())->toBe(1)
        ->and(taskBillingStatuses())->toBe(['pending_payment']);
});

it('generates an invoice for a subset of tasks', function () {
    $s = billableScenario();
    $task = Task::query()->orderBy('id')->first();

    BillingServer::tool(GenerateInvoice::class, [
        'project_id' => $s['project']->id,
        'task_ids' => [$task->id],
    ])->assertOk()->assertSee('"amount":200');

    expect(Invoice::first()->items)->toHaveCount(1);
});

it('reports when there is nothing to bill', function () {
    $s = billableScenario();
    generatedInvoice($s);

    BillingServer::tool(GenerateInvoice::class, ['project_id' => $s['project']->id])
        ->assertHasErrors(['no completed, billable tasks']);

    expect(Invoice::count())->toBe(1);
});

it('creates a manual invoice, claims the task and totals the items', function () {
    $s = billableScenario();
    $tasks = Task::query()->orderBy('id')->get();

    BillingServer::tool(CreateInvoice::class, [
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'notes' => 'Manual',
        'items' => [
            ['task_id' => $tasks[0]->id, 'unit_id' => $s['unit']->id, 'quantity' => 2, 'unit_price' => 50],
            ['task_id' => $tasks[1]->id, 'unit_id' => $s['unit']->id, 'quantity' => 1.5, 'unit_price' => 10],
        ],
    ])
        ->assertOk()
        ->assertSee('"status":"draft"')
        ->assertSee('"amount":115');

    $invoice = Invoice::first();

    expect($invoice->account_id)->toBe($s['account']->id)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items->first()->amount)->toEqual('100.00')
        ->and($tasks[0]->fresh()->billing_status)->toBe(TaskBillingStatus::PendingPayment)
        ->and($tasks[2]->fresh()->billing_status)->toBe(TaskBillingStatus::Billable);
});

it('validates manual invoice input', function (array $overrides, string $message) {
    $s = billableScenario();
    $task = Task::query()->first();

    $arguments = [
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'items' => [['task_id' => $task->id, 'unit_id' => $s['unit']->id, 'quantity' => 1, 'unit_price' => 10]],
        ...$overrides,
    ];

    BillingServer::tool(CreateInvoice::class, $arguments)->assertHasErrors([$message]);

    expect(Invoice::count())->toBe(0);
})->with([
    'no items' => [['items' => []], 'Validation failed'],
    'negative price' => [['items' => [['task_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => -5]]], 'Validation failed'],
    'missing unit' => [['items' => [['task_id' => 1, 'quantity' => 1, 'unit_price' => 5]]], 'Validation failed'],
]);

it('rejects manual invoices with a mismatched project, foreign unit, or unavailable task', function () {
    $s = billableScenario();
    $task = Task::query()->orderBy('id')->first();
    $otherClient = Client::factory()->for($s['account'])->create();
    $foreignUnit = makeQuoteScenario()['unit'];
    $item = ['task_id' => $task->id, 'unit_id' => $s['unit']->id, 'quantity' => 1, 'unit_price' => 10];

    BillingServer::tool(CreateInvoice::class, [
        'client_id' => $otherClient->id,
        'project_id' => $s['project']->id,
        'items' => [$item],
    ])->assertHasErrors(['does not belong to that client']);

    BillingServer::tool(CreateInvoice::class, [
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'items' => [[...$item, 'unit_id' => $foreignUnit->id]],
    ])->assertHasErrors(['unit was not found']);

    $task->update(['billing_status' => TaskBillingStatus::PendingPayment]);

    BillingServer::tool(CreateInvoice::class, [
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'items' => [$item],
    ])->assertHasErrors(['not billable']);

    expect(Invoice::count())->toBe(0);
});

it('does not let a manual invoice bill a task of another project', function () {
    $s = billableScenario();
    $foreignTask = Task::factory()->create(['billing_status' => TaskBillingStatus::Billable]);

    BillingServer::tool(CreateInvoice::class, [
        'client_id' => $s['client']->id,
        'project_id' => $s['project']->id,
        'items' => [['task_id' => $foreignTask->id, 'unit_id' => $s['unit']->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertHasErrors(['not billable']);
});

it('sends a draft invoice by email and marks it sent', function () {
    Mail::fake();
    $s = billableScenario();
    $invoice = generatedInvoice($s);

    BillingServer::tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertSee('"status":"sent"');

    Mail::assertQueued(InvoiceMailSent::class);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('refuses to send an invoice that is not a draft', function () {
    Mail::fake();
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);

    BillingServer::tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Only a draft invoice']);

    Mail::assertNothingSent();
});

it('marks a sent invoice paid, settles tasks and records the sale', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);

    BillingServer::tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertSee('"status":"paid"');

    $sale = Sale::first();

    expect(taskBillingStatuses())->toBe(['paid'])
        ->and($sale->account_id)->toBe($s['account']->id)
        ->and($sale->reference_key)->toBe($invoice->number)
        ->and((float) $sale->amount)->toBe(850.0);
});

it('only marks sent invoices as paid', function (InvoiceStatus $status) {
    $s = billableScenario();
    $invoice = generatedInvoice($s, $status);

    BillingServer::tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Only a sent invoice']);

    expect(Sale::count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe($status);
})->with([InvoiceStatus::Draft, InvoiceStatus::Paid, InvoiceStatus::Void]);

it('voids an invoice and releases its tasks', function (InvoiceStatus $status) {
    $s = billableScenario();
    $invoice = generatedInvoice($s, $status);

    BillingServer::tool(VoidInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertSee('"status":"void"');

    expect(taskBillingStatuses())->toBe(['billable']);
})->with([InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Overdue]);

it('refuses to void a paid or already voided invoice', function (InvoiceStatus $status) {
    $s = billableScenario();
    $invoice = generatedInvoice($s, $status);

    BillingServer::tool(VoidInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Only a draft, sent or overdue']);
})->with([InvoiceStatus::Paid, InvoiceStatus::Void]);

it('returns the invoice pdf as base64', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s);

    BillingServer::tool(GetInvoicePdf::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertSee('"filename":"Invoice-')
        ->assertSee('JVBERi0');
});

it('acts only on invoices of the account the token is bound to', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);
    $second = Account::factory()->for($s['owner'], 'owner')->create();
    actingAsToken($s['owner'], ['*'], $second);

    BillingServer::tool(VoidInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['not found']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('requires the invoices:write ability for writes but not reads', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);
    actingAsToken($s['owner'], ['tasks:write', 'quotes:write']);

    BillingServer::tool(ListInvoices::class)->assertOk();
    BillingServer::tool(GetInvoice::class, ['invoice_id' => $invoice->id])->assertOk();

    foreach ([MarkInvoicePaid::class, VoidInvoice::class, SendInvoice::class] as $tool) {
        BillingServer::tool($tool, ['invoice_id' => $invoice->id])->assertHasErrors(['invoices:write']);
    }

    BillingServer::tool(GenerateInvoice::class, ['project_id' => $s['project']->id])->assertHasErrors(['invoices:write']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('limits invoice writes to owners and managers', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);
    $member = makeAccountUser($s['account'], AccountRole::Member);
    $manager = makeAccountUser($s['account'], AccountRole::Manager);

    actingAsToken($member, ['*']);
    BillingServer::tool(ListInvoices::class)->assertOk();

    foreach ([MarkInvoicePaid::class, VoidInvoice::class, SendInvoice::class] as $tool) {
        BillingServer::tool($tool, ['invoice_id' => $invoice->id])
            ->assertHasErrors(['owner or manager']);
    }

    BillingServer::tool(GenerateInvoice::class, ['project_id' => $s['project']->id])
        ->assertHasErrors(['owner or manager']);

    actingAsToken($manager, ['*']);
    BillingServer::tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('does not let a user from another account touch an invoice', function () {
    $s = billableScenario();
    $invoice = generatedInvoice($s, InvoiceStatus::Sent);
    $stranger = User::factory()->create();
    Account::factory()->for($stranger, 'owner')->create();
    actingAsToken($stranger, ['*']);

    BillingServer::tool(VoidInvoice::class, ['invoice_id' => $invoice->id])->assertHasErrors(['not found']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});
