<?php

namespace App\Filament\App\Clusters\Reports\Widgets;

use App\Services\Reports\SalesReportService;
use Filament\Facades\Filament;

/**
 * Same year filter and description as the monthly chart, bucketed by calendar quarter.
 */
class SalesByQuarterChart extends SalesByMonthChart
{
    protected ?string $heading = 'Quarterly sales';

    protected function getData(): array
    {
        $totals = app(SalesReportService::class)->quarterlyTotals(Filament::getTenant(), $this->selectedYear());

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_values($totals),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#36A2EB',
                ],
            ],
            'labels' => array_map(fn (int $quarter): string => "Q{$quarter}", array_keys($totals)),
        ];
    }
}
