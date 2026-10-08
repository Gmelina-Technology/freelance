<?php

use App\Enums\AccountRole;
use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\Clients\Pages\ManageClientInvoices;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->owner = makeAccountUser($this->account, AccountRole::Owner);
    $this->client = Client::factory()->create(['account_id' => $this->account->id]);

    $otherAccount = Account::factory()->create();
    Invoice::factory()->create([
        'account_id' => $otherAccount->id,
        'client_id' => Client::factory()->create(['account_id' => $otherAccount->id])->id,
        'status' => InvoiceStatus::Overdue,
    ]);

    Model::preventLazyLoading(false);

    bindFilamentTenant($this->owner, $this->account);
});

function sortingInvoice(object $test, InvoiceStatus $status, ?string $dueDate, string $issuedAt = '2026-01-01'): Invoice
{
    return Invoice::factory()->create([
        'account_id' => $test->account->id,
        'client_id' => $test->client->id,
        'status' => $status,
        'due_date' => $dueDate,
        'issued_at' => $issuedAt,
    ]);
}

function sortingTables(object $test): array
{
    return [
        'invoices list' => fn () => Livewire::test(ListInvoices::class),
        'client invoices' => fn () => Livewire::test(ManageClientInvoices::class, ['record' => $test->client->getRouteKey()]),
    ];
}

it('lists every status exactly once in the priority order', function () {
    $cases = InvoiceStatus::inPriorityOrder();

    expect($cases)->toHaveCount(count(InvoiceStatus::cases()))
        ->and(collect($cases)->unique())->toHaveCount(count(InvoiceStatus::cases()))
        ->and($cases)->toBe([
            InvoiceStatus::Overdue,
            InvoiceStatus::Sent,
            InvoiceStatus::Draft,
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
        ])
        ->and(InvoiceStatus::Sent->priority())->toBe(1);
});

it('treats a hostile direction as ascending', function () {
    $sent = sortingInvoice($this, InvoiceStatus::Sent, '2026-03-01');
    $overdue = sortingInvoice($this, InvoiceStatus::Overdue, '2026-03-01');

    $ids = Invoice::query()
        ->orderByStatusThenDueDate('desc; DROP TABLE invoices;--')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$overdue->id, $sent->id]);
});

it('sorts unknown status strings after every known status', function () {
    $void = sortingInvoice($this, InvoiceStatus::Void, '2026-03-01');
    $unknown = sortingInvoice($this, InvoiceStatus::Draft, '2026-03-01');
    DB::table('invoices')->where('id', $unknown->id)->update(['status' => 'mystery']);

    expect(Invoice::query()->orderByStatusThenDueDate()->pluck('id')->all())
        ->toBe([$void->id, $unknown->id]);
});

foreach (['invoices list', 'client invoices'] as $tableName) {
    describe($tableName, function () use ($tableName) {
        it('defaults to status priority then earliest due date, nulls last', function () use ($tableName) {
            $void = sortingInvoice($this, InvoiceStatus::Void, '2026-01-01');
            $draft = sortingInvoice($this, InvoiceStatus::Draft, '2026-02-01');
            $sentFuture = sortingInvoice($this, InvoiceStatus::Sent, '2099-01-01');
            $sentNull = sortingInvoice($this, InvoiceStatus::Sent, null);
            $paid = sortingInvoice($this, InvoiceStatus::Paid, '2026-02-01');
            $sentPast = sortingInvoice($this, InvoiceStatus::Sent, '2020-01-01');
            $overdue = sortingInvoice($this, InvoiceStatus::Overdue, '2026-02-01');

            sortingTables($this)[$tableName]()
                ->assertCanSeeTableRecords(
                    [$overdue, $sentPast, $sentFuture, $sentNull, $draft, $paid, $void],
                    inOrder: true,
                );
        });

        it('sorts the status column ascending like the default and descending reversed', function () use ($tableName) {
            $overdue = sortingInvoice($this, InvoiceStatus::Overdue, '2026-02-01');
            $sentLate = sortingInvoice($this, InvoiceStatus::Sent, '2026-05-01');
            $sentEarly = sortingInvoice($this, InvoiceStatus::Sent, '2026-04-01');
            $draft = sortingInvoice($this, InvoiceStatus::Draft, '2026-02-01');
            $void = sortingInvoice($this, InvoiceStatus::Void, '2026-02-01');

            sortingTables($this)[$tableName]()
                ->sortTable('status')
                ->assertCanSeeTableRecords([$overdue, $sentEarly, $sentLate, $draft, $void], inOrder: true);

            sortingTables($this)[$tableName]()
                ->sortTable('status', 'desc')
                ->assertCanSeeTableRecords([$void, $draft, $sentEarly, $sentLate, $overdue], inOrder: true);
        });
    });
}

it('lets the due date header override the default status order on the main table', function () {
    $overdueLate = sortingInvoice($this, InvoiceStatus::Overdue, '2026-09-01');
    $paidEarly = sortingInvoice($this, InvoiceStatus::Paid, '2026-01-01');
    $draftMid = sortingInvoice($this, InvoiceStatus::Draft, '2026-05-01');

    Livewire::test(ListInvoices::class)
        ->sortTable('due_date')
        ->assertCanSeeTableRecords([$paidEarly, $draftMid, $overdueLate], inOrder: true);
});

it('lets the issued at header override the default status order on the main table', function () {
    $overdue = sortingInvoice($this, InvoiceStatus::Overdue, '2026-09-01', '2026-03-01');
    $paid = sortingInvoice($this, InvoiceStatus::Paid, '2026-01-01', '2026-01-01');

    Livewire::test(ListInvoices::class)
        ->sortTable('issued_at')
        ->assertCanSeeTableRecords([$paid, $overdue], inOrder: true);
});

it('keeps other accounts invoices out of both tables', function () {
    $mine = sortingInvoice($this, InvoiceStatus::Sent, '2026-03-01');

    foreach (sortingTables($this) as $makeTable) {
        $records = $makeTable()->instance()->getTableRecords();

        expect($records->pluck('id')->all())->toBe([$mine->id]);
    }
});
