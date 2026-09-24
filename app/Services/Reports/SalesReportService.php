<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SalesReportService
{
    /**
     * Every Sale row of the account counts as a sale. Anything that reports sales builds on this.
     *
     * @return Builder<Sale>
     */
    public function query(Account $account): Builder
    {
        return Sale::query()->where('sales.account_id', $account->getKey());
    }

    /**
     * Sum sales into half-open [start, end) buckets with a single query.
     *
     * @param  array<array-key, array{0: CarbonImmutable, 1: CarbonImmutable}>  $buckets
     * @return array<array-key, float> keyed like $buckets
     */
    public function totalsForBuckets(Account $account, array $buckets): array
    {
        if ($buckets === []) {
            return [];
        }

        $columns = [];
        $bindings = [];

        foreach (array_values($buckets) as $index => [$start, $end]) {
            $columns[] = "SUM(CASE WHEN sales.transaction_date >= ? AND sales.transaction_date < ? THEN sales.amount ELSE 0 END) AS bucket_{$index}";
            $bindings[] = $this->toDateTimeString($start);
            $bindings[] = $this->toDateTimeString($end);
        }

        $starts = array_map(fn (array $bucket): CarbonImmutable => $bucket[0], $buckets);
        $ends = array_map(fn (array $bucket): CarbonImmutable => $bucket[1], $buckets);

        $row = $this->query($account)
            ->where('sales.transaction_date', '>=', $this->toDateTimeString(min($starts)))
            ->where('sales.transaction_date', '<', $this->toDateTimeString(max($ends)))
            ->toBase()
            ->selectRaw(implode(', ', $columns), $bindings)
            ->first();

        $totals = [];

        foreach (array_keys($buckets) as $index => $key) {
            $totals[$key] = (float) ($row?->{"bucket_{$index}"} ?? 0);
        }

        return $totals;
    }

    /**
     * @return array<int, float> keyed 1..12
     */
    public function monthlyTotals(Account $account, int $year): array
    {
        $buckets = [];

        foreach (range(1, 12) as $month) {
            $start = $this->startOfMonth($year, $month);
            $buckets[$month] = [$start, $start->addMonth()];
        }

        return $this->totalsForBuckets($account, $buckets);
    }

    /**
     * @return array<int, float> keyed 1..4
     */
    public function quarterlyTotals(Account $account, int $year): array
    {
        $buckets = [];

        foreach (range(1, 4) as $quarter) {
            $start = $this->startOfMonth($year, $quarter * 3 - 2);
            $buckets[$quarter] = [$start, $start->addMonths(3)];
        }

        return $this->totalsForBuckets($account, $buckets);
    }

    /**
     * Totals per calendar year, from the year of the first sale up to the current year.
     *
     * @return array<int, float> keyed by year, ascending
     */
    public function yearlyTotals(Account $account): array
    {
        $currentYear = now()->year;
        $firstYear = min($this->firstSaleYear($account) ?? $currentYear, $currentYear);

        $buckets = [];

        foreach (range($firstYear, $currentYear) as $year) {
            $buckets[$year] = [$this->startOfMonth($year, 1), $this->startOfMonth($year + 1, 1)];
        }

        return $this->totalsForBuckets($account, $buckets);
    }

    public function firstSaleYear(Account $account): ?int
    {
        $first = $this->query($account)->min('sales.transaction_date');

        if ($first === null) {
            return null;
        }

        return CarbonImmutable::parse($first, config('app.timezone'))->year;
    }

    /**
     * Years a report can be filtered by: the current year back to the first sale's year.
     *
     * @return list<int> descending
     */
    public function availableYears(Account $account): array
    {
        $currentYear = now()->year;
        $firstYear = min($this->firstSaleYear($account) ?? $currentYear, $currentYear);

        return range($currentYear, $firstYear);
    }

    private function startOfMonth(int $year, int $month): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
    }

    private function toDateTimeString(CarbonImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
