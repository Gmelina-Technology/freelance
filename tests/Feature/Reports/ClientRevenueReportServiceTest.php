<?php

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Sale;
use App\Services\Reports\ClientRevenueReportService;
use App\Services\Reports\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// No Filament tenant is bound in this file: the service's own account filters must isolate on their own.

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

    $this->account = Account::factory()->create();
    $this->service = app(ClientRevenueReportService::class);
});

function paidInvoice(Account $account, Client $client, ?Project $project, float $amount): Invoice
{
    return Invoice::factory()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'project_id' => $project?->id,
        'amount' => $amount,
        'status' => InvoiceStatus::Paid,
    ]);
}

function invoicedSale(Account $account, Client $client, ?Project $project, float $amount, string $at = '2026-03-01 10:00:00'): Sale
{
    return Sale::factory()
        ->forInvoice(paidInvoice($account, $client, $project, $amount))
        ->create(['transaction_date' => $at]);
}

/**
 * @param  array{clients: list<array<string, mixed>>, unattributed_total: float}  $report
 * @return array<string, mixed>
 */
function reportClient(array $report, string $name): array
{
    return collect($report['clients'])->firstWhere('client_name', $name)
        ?? throw new RuntimeException("Client {$name} is not in the report.");
}

it('attributes sales to the invoice client and ranks its projects', function () {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $website = Project::factory()->for($this->account)->for($acme)->create(['name' => 'Website']);
    $seo = Project::factory()->for($this->account)->for($acme)->create(['name' => 'SEO']);

    invoicedSale($this->account, $acme, $website, 500);
    invoicedSale($this->account, $acme, $website, 300);
    invoicedSale($this->account, $acme, $seo, 300);
    invoicedSale($this->account, $acme, null, 900);

    $client = reportClient($this->service->forAccount($this->account), 'Acme');

    expect($client['total'])->toEqualWithDelta(2000, 0.001)
        ->and($client['projects'])->toHaveCount(3)
        ->and(array_column($client['projects'], 'project_name'))->toBe(['Website', 'SEO', 'No project'])
        ->and(array_column($client['projects'], 'top_label'))->toBe(['Top contributor', null, null])
        ->and(array_column($client['projects'], 'share'))->toBe([40.0, 15.0, 45.0])
        ->and($client['projects'][2]['project_id'])->toBeNull()
        ->and($client['projects'][0]['project_id'])->toBe($website->id);
});

it('breaks ties between projects by name and marks exactly one top contributor', function () {
    $globex = Client::factory()->for($this->account)->create(['name' => 'Globex']);
    $beta = Project::factory()->for($this->account)->for($globex)->create(['name' => 'Beta']);
    $alpha = Project::factory()->for($this->account)->for($globex)->create(['name' => 'Alpha']);

    invoicedSale($this->account, $globex, $beta, 250);
    invoicedSale($this->account, $globex, $alpha, 250);

    $projects = reportClient($this->service->forAccount($this->account), 'Globex')['projects'];

    expect(array_column($projects, 'project_name'))->toBe(['Alpha', 'Beta'])
        ->and(array_filter(array_column($projects, 'top_label')))->toBe([0 => 'Top contributor']);
});

it('sorts clients by total then name', function () {
    foreach (['Zeta' => 100, 'Beta' => 400, 'Alpha' => 100] as $name => $amount) {
        invoicedSale($this->account, Client::factory()->for($this->account)->create(['name' => $name]), null, $amount);
    }

    expect(array_column($this->service->forAccount($this->account)['clients'], 'client_name'))
        ->toBe(['Beta', 'Alpha', 'Zeta']);
});

it('lists a project under the invoice client even when the project belongs to another client', function () {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $globex = Client::factory()->for($this->account)->create(['name' => 'Globex']);
    $globexProject = Project::factory()->for($this->account)->for($globex)->create(['name' => 'Globex rollout']);

    invoicedSale($this->account, $acme, $globexProject, 400);

    $report = $this->service->forAccount($this->account);

    expect($report['clients'])->toHaveCount(1)
        ->and(reportClient($report, 'Acme')['projects'][0]['project_name'])->toBe('Globex rollout');
});

it('leaves out clients and projects without sales', function () {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $idle = Project::factory()->for($this->account)->for($acme)->create(['name' => 'Idle project']);
    $busy = Project::factory()->for($this->account)->for($acme)->create(['name' => 'Busy project']);
    $quiet = Client::factory()->for($this->account)->create(['name' => 'Quiet client']);

    invoicedSale($this->account, $acme, $busy, 100);
    paidInvoice($this->account, $acme, $idle, 900);
    paidInvoice($this->account, $quiet, null, 900);

    $report = $this->service->forAccount($this->account);

    expect(array_column($report['clients'], 'client_name'))->toBe(['Acme'])
        ->and(array_column($report['clients'][0]['projects'], 'project_name'))->toBe(['Busy project']);
});

it('narrows by transaction year and treats a null year as all time', function () {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $old = Project::factory()->for($this->account)->for($acme)->create(['name' => 'Old project']);
    $new = Project::factory()->for($this->account)->for($acme)->create(['name' => 'New project']);

    invoicedSale($this->account, $acme, $old, 100, '2025-12-31 23:59:59');
    invoicedSale($this->account, $acme, $new, 200, '2026-01-01 00:00:00');

    $thisYear = reportClient($this->service->forAccount($this->account, 2026), 'Acme');
    $lastYear = reportClient($this->service->forAccount($this->account, 2025), 'Acme');
    $allTime = reportClient($this->service->forAccount($this->account), 'Acme');

    expect(array_column($thisYear['projects'], 'project_name'))->toBe(['New project'])
        ->and(array_column($lastYear['projects'], 'project_name'))->toBe(['Old project'])
        ->and($allTime['total'])->toEqualWithDelta(300, 0.001)
        ->and($this->service->forAccount($this->account, 2024)['clients'])->toBe([]);
});

it('reconciles client totals and unattributed sales with the sales total', function (?int $year) {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $website = Project::factory()->for($this->account)->for($acme)->create(['name' => 'Website']);
    $doomed = Client::factory()->for($this->account)->create(['name' => 'Doomed client']);

    invoicedSale($this->account, $acme, $website, 500);
    invoicedSale($this->account, $acme, null, 120, '2025-08-01 10:00:00');
    Sale::factory()->for($this->account)->create(['amount' => 50, 'transaction_date' => '2026-02-01 10:00:00']);
    invoicedSale($this->account, $acme, null, 70)->invoice->delete();
    invoicedSale($this->account, $doomed, null, 90, '2025-04-01 10:00:00');
    $doomed->delete();

    $report = $this->service->forAccount($this->account, $year);

    $sales = app(SalesReportService::class);
    $expected = $year === null
        ? (float) $sales->query($this->account)->sum('sales.amount')
        : array_sum($sales->totalsForBuckets($this->account, [[
            CarbonImmutable::create($year, 1, 1),
            CarbonImmutable::create($year + 1, 1, 1),
        ]]));

    expect(array_sum(array_column($report['clients'], 'total')) + $report['unattributed_total'])
        ->toEqualWithDelta($expected, 0.001)
        ->and($report['unattributed_total'])->toEqualWithDelta($year === null ? 210 : ($year === 2026 ? 120 : 90), 0.001)
        ->and(array_column($report['clients'], 'client_name'))->not->toContain('Doomed client');
})->with([
    'all time' => [null],
    '2026' => [2026],
    '2025' => [2025],
]);

it('counts a sale matching another account invoice number as unattributed', function () {
    $other = Account::factory()->create();
    $otherClient = Client::factory()->for($other)->create(['name' => 'Rival Corp']);
    $otherInvoice = paidInvoice($other, $otherClient, null, 800);

    Sale::factory()->for($this->account)->create(['reference_key' => $otherInvoice->number, 'amount' => 45]);

    $report = $this->service->forAccount($this->account);

    expect($report['clients'])->toBe([])
        ->and($report['unattributed_total'])->toEqualWithDelta(45, 0.001)
        ->and(json_encode($report))->not->toContain('Rival Corp');
});

it('does not attribute through another account invoice even when it points at this account client', function () {
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);
    $otherInvoice = paidInvoice(Account::factory()->create(), $acme, null, 800);

    Sale::factory()->for($this->account)->create(['reference_key' => $otherInvoice->number, 'amount' => 45]);

    $report = $this->service->forAccount($this->account);

    expect($report['clients'])->toBe([])
        ->and($report['unattributed_total'])->toEqualWithDelta(45, 0.001);
});

it('shows no project for an invoice pointing at another account project', function () {
    $other = Account::factory()->create();
    $otherProject = Project::factory()->for($other)->create(['name' => 'Rival project']);
    $acme = Client::factory()->for($this->account)->create(['name' => 'Acme']);

    invoicedSale($this->account, $acme, $otherProject, 300);

    $report = $this->service->forAccount($this->account);

    expect(reportClient($report, 'Acme')['projects'])->toHaveCount(1)
        ->and(reportClient($report, 'Acme')['projects'][0]['project_name'])->toBe('No project')
        ->and(json_encode($report))->not->toContain('Rival project');
});

it('counts a sale on an invoice pointing at another account client as unattributed', function () {
    $otherClient = Client::factory()->for(Account::factory())->create(['name' => 'Rival Corp']);

    invoicedSale($this->account, $otherClient, null, 300);

    $report = $this->service->forAccount($this->account);

    expect($report['clients'])->toBe([])
        ->and($report['unattributed_total'])->toEqualWithDelta(300, 0.001)
        ->and(json_encode($report))->not->toContain('Rival Corp');
});

it('never counts another account sales', function () {
    $other = Account::factory()->create();
    $otherClient = Client::factory()->for($other)->create(['name' => 'Rival Corp']);
    invoicedSale($other, $otherClient, null, 999);
    Sale::factory()->for($other)->create(['amount' => 555]);

    $report = $this->service->forAccount($this->account);

    expect($report)->toBe(['clients' => [], 'unattributed_total' => 0.0]);
});

it('runs the same number of queries regardless of volume', function () {
    $small = Account::factory()->create();
    invoicedSale($small, Client::factory()->for($small)->create(), Project::factory()->for($small)->create(), 100);

    $large = Account::factory()->create();
    Client::factory()->for($large)->count(20)->create()->each(function (Client $client) use ($large): void {
        Project::factory()->for($large)->for($client)->count(3)->create()->each(function (Project $project) use ($large, $client): void {
            foreach (['2024-05-01 10:00:00', '2025-05-01 10:00:00', '2026-05-01 10:00:00'] as $at) {
                invoicedSale($large, $client, $project, 100, $at);
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
