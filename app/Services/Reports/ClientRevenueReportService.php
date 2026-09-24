<?php

namespace App\Services\Reports;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class ClientRevenueReportService
{
    public function __construct(private SalesReportService $sales) {}

    /**
     * Sales per client, broken down by the invoice's project. Sales that can't be traced to
     * one of the account's clients are totalled as unattributed so everything reconciles.
     *
     * @return array{
     *     clients: list<array{
     *         client_id: int,
     *         client_name: string,
     *         currency_code: ?string,
     *         total: float,
     *         projects: list<array{
     *             project_id: ?int,
     *             project_name: string,
     *             amount: float,
     *             share: float,
     *             top_label: ?string,
     *         }>,
     *     }>,
     *     unattributed_total: float,
     * }
     */
    public function forAccount(Account $account, ?int $year = null): array
    {
        $accountId = $account->getKey();

        // Account filters sit in the ON clauses: in WHERE they would turn these into inner
        // joins and silently drop unattributed sales.
        $query = $this->sales->query($account)
            ->leftJoin('invoices', fn (JoinClause $join) => $join
                ->on('invoices.number', '=', 'sales.reference_key')
                ->where('invoices.account_id', $accountId))
            ->leftJoin('clients', fn (JoinClause $join) => $join
                ->on('clients.id', '=', 'invoices.client_id')
                ->where('clients.account_id', $accountId))
            ->leftJoin('projects', fn (JoinClause $join) => $join
                ->on('projects.id', '=', 'invoices.project_id')
                ->where('projects.account_id', $accountId))
            ->select([
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.currency_code',
                'projects.id as project_id',
                'projects.name as project_name',
            ])
            ->selectRaw('SUM(sales.amount) as amount')
            ->groupBy('clients.id', 'clients.name', 'clients.currency_code', 'projects.id', 'projects.name');

        if ($year !== null) {
            $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, config('app.timezone'));

            $query->where('sales.transaction_date', '>=', $start->format('Y-m-d H:i:s'))
                ->where('sales.transaction_date', '<', $start->addYear()->format('Y-m-d H:i:s'));
        }

        $rows = $query->toBase()->get();

        $unattributedTotal = (float) $rows->whereNull('client_id')->sum(fn (object $row): float => (float) $row->amount);

        $clients = $rows->whereNotNull('client_id')
            ->filter(fn (object $row): bool => (float) $row->amount != 0)
            ->groupBy('client_id')
            ->map(fn (Collection $clientRows): array => $this->clientLine($clientRows))
            ->filter(fn (array $client): bool => $client['total'] != 0)
            ->sort(fn (array $a, array $b): int => [$b['total'], $a['client_name']] <=> [$a['total'], $b['client_name']])
            ->values()
            ->all();

        return [
            'clients' => $clients,
            'unattributed_total' => $unattributedTotal,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{client_id: int, client_name: string, currency_code: ?string, total: float, projects: list<array{project_id: ?int, project_name: string, amount: float, share: float, top_label: ?string}>}
     */
    private function clientLine(Collection $rows): array
    {
        $first = $rows->first();
        $total = (float) $rows->sum(fn (object $row): float => (float) $row->amount);

        $named = $rows->whereNotNull('project_id')
            ->sort(fn (object $a, object $b): int => [(float) $b->amount, $a->project_name] <=> [(float) $a->amount, $b->project_name])
            ->values()
            ->map(fn (object $row, int $rank): array => [
                'project_id' => (int) $row->project_id,
                'project_name' => $row->project_name,
                'amount' => (float) $row->amount,
                'share' => $this->share((float) $row->amount, $total),
                'top_label' => $rank === 0 ? 'Top contributor' : null,
            ]);

        // An invoice without a project, or pointing at a project outside the account.
        $noProjectAmount = (float) $rows->whereNull('project_id')->sum(fn (object $row): float => (float) $row->amount);

        if ($noProjectAmount != 0) {
            $named->push([
                'project_id' => null,
                'project_name' => 'No project',
                'amount' => $noProjectAmount,
                'share' => $this->share($noProjectAmount, $total),
                'top_label' => null,
            ]);
        }

        return [
            'client_id' => (int) $first->client_id,
            'client_name' => $first->client_name,
            'currency_code' => $first->currency_code,
            'total' => $total,
            'projects' => $named->all(),
        ];
    }

    private function share(float $amount, float $total): float
    {
        return $total == 0 ? 0.0 : round($amount / $total * 100, 1);
    }
}
