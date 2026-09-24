<?php

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Reports\ReceivablesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// No Filament tenant is bound in this file: the service's own account filters must isolate on their own.

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->account = Account::factory()->create();
    $this->client = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $this->project = Project::factory()->for($this->account)->for($this->client)->create(['name' => 'Website']);
    $this->service = app(ReceivablesReportService::class);
});

function receivable(Account $account, Client $client, ?Project $project, InvoiceStatus $status, float $amount, ?string $dueDate = '2026-07-01 00:00:00'): Invoice
{
    return Invoice::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'project_id' => $project?->id,
        'status' => $status,
        'amount' => $amount,
        'due_date' => $dueDate,
    ]);
}

/**
 * @param  array<string, mixed>  $line
 * @return array{pending: float, overdue: float, owed: float, collected: float}
 */
function receivableFigures(array $line): array
{
    return array_intersect_key($line, array_flip(['pending', 'overdue', 'owed', 'collected']));
}

it('maps every invoice status to its figures at client and project level', function (InvoiceStatus $status, ?array $expected) {
    // A past due date, so a Sent invoice is also overdue and a Paid one is still only collected.
    receivable($this->account, $this->client, $this->project, $status, 1000, '2026-06-01 00:00:00');

    $report = $this->service->forAccount($this->account);

    if ($expected === null) {
        expect($report)->toBe([]);

        return;
    }

    [$pending, $overdue, $collected] = $expected;
    $figures = ['pending' => $pending, 'overdue' => $overdue, 'owed' => $pending, 'collected' => $collected];

    expect($report)->toHaveCount(1)
        ->and(receivableFigures($report[0]))->toEqualWithDelta($figures, 0.001)
        ->and($report[0]['projects'])->toHaveCount(1)
        ->and($report[0]['projects'][0]['project_name'])->toBe('Website')
        ->and(receivableFigures($report[0]['projects'][0]))->toEqualWithDelta($figures, 0.001);
})->with([
    'draft' => [InvoiceStatus::Draft, null],
    'sent' => [InvoiceStatus::Sent, [1000, 1000, 0]],
    'paid' => [InvoiceStatus::Paid, [0, 0, 1000]],
    'overdue' => [InvoiceStatus::Overdue, [1000, 1000, 0]],
    'void' => [InvoiceStatus::Void, null],
]);

it('derives overdue from the due date against the start of today', function (InvoiceStatus $status, ?string $dueDate, bool $isOverdue) {
    receivable($this->account, $this->client, $this->project, $status, 400, $dueDate);

    $client = $this->service->forAccount($this->account)[0];

    expect($client['pending'])->toEqualWithDelta(400, 0.001)
        ->and($client['owed'])->toEqualWithDelta(400, 0.001)
        ->and($client['overdue'])->toEqualWithDelta($isOverdue ? 400 : 0, 0.001)
        ->and($client['collected'])->toEqualWithDelta(0, 0.001);
})->with([
    '(a) sent, due yesterday' => [InvoiceStatus::Sent, '2026-06-14 10:00:00', true],
    '(a) sent, due one second before today' => [InvoiceStatus::Sent, '2026-06-14 23:59:59', true],
    '(b) sent, due at midnight today' => [InvoiceStatus::Sent, '2026-06-15 00:00:00', false],
    '(b) sent, due earlier today' => [InvoiceStatus::Sent, '2026-06-15 08:00:00', false],
    '(b) sent, due at the end of today' => [InvoiceStatus::Sent, '2026-06-15 23:59:59', false],
    '(c) sent, due tomorrow' => [InvoiceStatus::Sent, '2026-06-16 10:00:00', false],
    '(d) sent, no due date' => [InvoiceStatus::Sent, null, false],
    '(e) overdue, due in the future' => [InvoiceStatus::Overdue, '2026-07-01 00:00:00', true],
    '(e) overdue, no due date' => [InvoiceStatus::Overdue, null, true],
]);

it('counts a paid invoice past its due date only as collected', function () {
    receivable($this->account, $this->client, $this->project, InvoiceStatus::Paid, 250, '2026-01-01 00:00:00');

    expect(receivableFigures($this->service->forAccount($this->account)[0]))->toEqualWithDelta([
        'pending' => 0.0,
        'overdue' => 0.0,
        'owed' => 0.0,
        'collected' => 250.0,
    ], 0.001);
});

it('leaves out a client with only draft and void invoices', function () {
    $idle = Client::factory()->for($this->account)->create(['name' => 'Idle client']);
    receivable($this->account, $idle, null, InvoiceStatus::Draft, 500);
    receivable($this->account, $idle, null, InvoiceStatus::Void, 500);
    receivable($this->account, $this->client, null, InvoiceStatus::Sent, 100);

    expect(array_column($this->service->forAccount($this->account), 'client_name'))->toBe(['Acme']);
});

it('breaks figures down per project with client totals summing the project rows', function () {
    $seo = Project::factory()->for($this->account)->for($this->client)->create(['name' => 'SEO']);

    receivable($this->account, $this->client, $this->project, InvoiceStatus::Sent, 2000);
    receivable($this->account, $this->client, $this->project, InvoiceStatus::Paid, 5000);
    receivable($this->account, $this->client, $seo, InvoiceStatus::Sent, 300, '2026-05-01 00:00:00');
    receivable($this->account, $this->client, $seo, InvoiceStatus::Draft, 999);
    receivable($this->account, $this->client, null, InvoiceStatus::Overdue, 9000);
    receivable($this->account, $this->client, null, InvoiceStatus::Paid, 4000);

    $client = $this->service->forAccount($this->account)[0];

    expect(array_column($client['projects'], 'project_name'))->toBe(['Website', 'SEO', 'No project'])
        ->and($client['projects'][2]['project_id'])->toBeNull()
        ->and(receivableFigures($client['projects'][2]))->toEqualWithDelta(['pending' => 9000.0, 'overdue' => 9000.0, 'owed' => 9000.0, 'collected' => 4000.0], 0.001)
        ->and(receivableFigures($client['projects'][1]))->toEqualWithDelta(['pending' => 300.0, 'overdue' => 300.0, 'owed' => 300.0, 'collected' => 0.0], 0.001);

    foreach (['pending', 'overdue', 'owed', 'collected'] as $figure) {
        expect($client[$figure])->toEqualWithDelta(array_sum(array_column($client['projects'], $figure)), 0.001);
    }

    expect($client['owed'])->toEqualWithDelta($client['pending'], 0.001)
        ->and($client['overdue'])->toBeLessThanOrEqual($client['pending']);
});

it('drops a project with nothing pending or collected', function () {
    $seo = Project::factory()->for($this->account)->for($this->client)->create(['name' => 'SEO']);
    receivable($this->account, $this->client, $this->project, InvoiceStatus::Sent, 100);
    receivable($this->account, $this->client, $seo, InvoiceStatus::Sent, 0);

    expect(array_column($this->service->forAccount($this->account)[0]['projects'], 'project_name'))->toBe(['Website']);
});

it('sorts clients by owed then collected then name', function () {
    $beta = Client::factory()->for($this->account)->create(['name' => 'Beta']);
    $alpha = Client::factory()->for($this->account)->create(['name' => 'Alpha']);
    $zeta = Client::factory()->for($this->account)->create(['name' => 'Zeta']);

    receivable($this->account, $this->client, null, InvoiceStatus::Sent, 100);
    receivable($this->account, $beta, null, InvoiceStatus::Sent, 900);
    receivable($this->account, $alpha, null, InvoiceStatus::Sent, 100);
    receivable($this->account, $alpha, null, InvoiceStatus::Paid, 50);
    receivable($this->account, $zeta, null, InvoiceStatus::Sent, 100);

    expect(array_column($this->service->forAccount($this->account), 'client_name'))
        ->toBe(['Beta', 'Alpha', 'Acme', 'Zeta']);
});

it('never includes another account invoices, overdue or not', function (InvoiceStatus $status, ?string $dueDate) {
    $other = Account::factory()->create();
    $otherClient = Client::factory()->for($other)->create(['name' => 'Rival Corp']);
    receivable($other, $otherClient, null, $status, 777, $dueDate);
    receivable($this->account, $this->client, null, InvoiceStatus::Sent, 100);

    $report = $this->service->forAccount($this->account);

    expect(receivableFigures($report[0]))->toEqualWithDelta(['pending' => 100.0, 'overdue' => 0.0, 'owed' => 100.0, 'collected' => 0.0], 0.001)
        ->and(json_encode($report))->not->toContain('Rival Corp');
})->with([
    'overdue by status' => [InvoiceStatus::Overdue, null],
    'sent past due' => [InvoiceStatus::Sent, '2026-01-01 00:00:00'],
    'paid' => [InvoiceStatus::Paid, null],
]);

it('ignores another account invoice even when it points at this account client', function (InvoiceStatus $status) {
    receivable(Account::factory()->create(), $this->client, $this->project, $status, 777, '2026-01-01 00:00:00');

    expect($this->service->forAccount($this->account))->toBe([]);
})->with([InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid]);

it('shows no project for an invoice pointing at another account project', function () {
    $otherProject = Project::factory()->for(Account::factory())->create(['name' => 'Rival project']);
    receivable($this->account, $this->client, $otherProject, InvoiceStatus::Sent, 300);

    $report = $this->service->forAccount($this->account);

    expect(array_column($report[0]['projects'], 'project_name'))->toBe(['No project'])
        ->and(json_encode($report))->not->toContain('Rival project');
});

it('excludes an invoice pointing at another account client', function () {
    $otherClient = Client::factory()->for(Account::factory())->create(['name' => 'Rival Corp']);
    receivable($this->account, $otherClient, null, InvoiceStatus::Overdue, 300);

    expect($this->service->forAccount($this->account))->toBe([]);
});

it('runs the same number of queries regardless of volume', function () {
    $small = Account::factory()->create();
    receivable($small, Client::factory()->for($small)->create(), null, InvoiceStatus::Sent, 100);

    $large = Account::factory()->create();
    Client::factory()->for($large)->count(20)->create()->each(function (Client $client) use ($large): void {
        Project::factory()->for($large)->for($client)->count(3)->create()->each(function (Project $project) use ($large, $client): void {
            foreach ([InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid] as $status) {
                receivable($large, $client, $project, $status, 100);
            }
        });
    });

    $count = function (Account $account): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->forAccount($account);

        return count(DB::getQueryLog());
    };

    expect($count($small))->toBe($count($large))->toBe(1);
});
