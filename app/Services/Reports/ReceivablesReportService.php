<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class ReceivablesReportService
{
    /**
     * As-of-now receivables per client and project.
     *
     * Pending is every outstanding invoice (Sent or Overdue) and is what is owed. Overdue is the
     * part of pending that is status Overdue or Sent past its due date. Collected is Paid and is
     * never owed. Draft and Void are not obligations and appear nowhere.
     *
     * @return list<array{
     *     client_id: int,
     *     client_name: string,
     *     currency_code: ?string,
     *     pending: float,
     *     overdue: float,
     *     owed: float,
     *     collected: float,
     *     projects: list<array{
     *         project_id: ?int,
     *         project_name: string,
     *         pending: float,
     *         overdue: float,
     *         owed: float,
     *         collected: float,
     *     }>,
     * }>
     */
    public function forAccount(Account $account): array
    {
        $accountId = $account->getKey();
        $startOfToday = CarbonImmutable::now(config('app.timezone'))->startOfDay()->format('Y-m-d H:i:s');

        $sent = InvoiceStatus::Sent->value;
        $overdue = InvoiceStatus::Overdue->value;
        $paid = InvoiceStatus::Paid->value;

        $rows = Invoice::query()
            ->join('clients', fn (JoinClause $join) => $join
                ->on('clients.id', '=', 'invoices.client_id')
                ->where('clients.account_id', $accountId))
            ->leftJoin('projects', fn (JoinClause $join) => $join
                ->on('projects.id', '=', 'invoices.project_id')
                ->where('projects.account_id', $accountId))
            ->where('invoices.account_id', $accountId)
            ->whereIn('invoices.status', [$sent, $overdue, $paid])
            ->select([
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.currency_code',
                'projects.id as project_id',
                'projects.name as project_name',
            ])
            ->selectRaw('SUM(CASE WHEN invoices.status IN (?, ?) THEN invoices.amount ELSE 0 END) as pending', [$sent, $overdue])
            ->selectRaw(
                'SUM(CASE WHEN invoices.status = ? OR (invoices.status = ? AND invoices.due_date < ?) THEN invoices.amount ELSE 0 END) as overdue',
                [$overdue, $sent, $startOfToday],
            )
            ->selectRaw('SUM(CASE WHEN invoices.status = ? THEN invoices.amount ELSE 0 END) as collected', [$paid])
            ->groupBy('clients.id', 'clients.name', 'clients.currency_code', 'projects.id', 'projects.name')
            ->toBase()
            ->get();

        return $rows
            ->groupBy('client_id')
            ->map(fn (Collection $clientRows): array => $this->clientLine($clientRows))
            ->filter(fn (array $client): bool => $client['projects'] !== [])
            ->sort(fn (array $a, array $b): int => $this->compare($a, $b, 'client_name'))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{client_id: int, client_name: string, currency_code: ?string, pending: float, overdue: float, owed: float, collected: float, projects: list<array{project_id: ?int, project_name: string, pending: float, overdue: float, owed: float, collected: float}>}
     */
    private function clientLine(Collection $rows): array
    {
        $projects = $rows
            ->map(fn (object $row): array => [
                'project_id' => $row->project_id === null ? null : (int) $row->project_id,
                'project_name' => $row->project_name ?? 'No project',
                'pending' => (float) $row->pending,
                'overdue' => (float) $row->overdue,
                'owed' => (float) $row->pending,
                'collected' => (float) $row->collected,
            ])
            ->reject(fn (array $project): bool => $project['pending'] == 0 && $project['collected'] == 0)
            ->sort(fn (array $a, array $b): int => ($a['project_id'] === null) <=> ($b['project_id'] === null)
                ?: $this->compare($a, $b, 'project_name'))
            ->values();

        $first = $rows->first();

        return [
            'client_id' => (int) $first->client_id,
            'client_name' => $first->client_name,
            'currency_code' => $first->currency_code,
            'pending' => (float) $projects->sum('pending'),
            'overdue' => (float) $projects->sum('overdue'),
            'owed' => (float) $projects->sum('owed'),
            'collected' => (float) $projects->sum('collected'),
            'projects' => $projects->all(),
        ];
    }

    /**
     * Most owed first, then most collected, then by name.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compare(array $a, array $b, string $nameKey): int
    {
        return [$b['owed'], $b['collected'], $a[$nameKey]] <=> [$a['owed'], $a['collected'], $b[$nameKey]];
    }
}
