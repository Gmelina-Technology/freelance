<?php

namespace App\Filament\App\Clusters\Reports\Widgets;

use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Services\Reports\SalesReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Number;

class SalesByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Monthly sales';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var list<int>|null
     */
    protected ?array $cachedAvailableYears = null;

    public static function canView(): bool
    {
        return ReportsCluster::canAccess();
    }

    public function mount(): void
    {
        $this->filter ??= (string) now()->year;

        parent::mount();
    }

    public function getDescription(): string
    {
        $total = array_sum($this->getCachedData()['datasets'][0]['data']);

        return "Total for {$this->selectedYear()}: ".Number::currency($total, in: Filament::getTenant()->currency_code ?? 'USD');
    }

    /**
     * @return array<int, string>
     */
    protected function getFilters(): array
    {
        $years = $this->availableYears();

        return array_combine($years, array_map('strval', $years));
    }

    protected function getData(): array
    {
        $totals = app(SalesReportService::class)->monthlyTotals(Filament::getTenant(), $this->selectedYear());

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_values($totals),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#36A2EB',
                ],
            ],
            'labels' => array_map(fn (int $month): string => date('M', mktime(0, 0, 0, $month, 1)), array_keys($totals)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * The filter arrives from the browser, so anything outside the account's years falls back to this year.
     */
    protected function selectedYear(): int
    {
        $year = (int) $this->filter;

        return in_array($year, $this->availableYears(), true) ? $year : now()->year;
    }

    /**
     * @return list<int>
     */
    protected function availableYears(): array
    {
        return $this->cachedAvailableYears ??= app(SalesReportService::class)->availableYears(Filament::getTenant());
    }
}
