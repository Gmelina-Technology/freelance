<?php

namespace App\Filament\App\Clusters\Reports\Widgets;

use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Services\Reports\SalesReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Number;

class SalesByYearChart extends ChartWidget
{
    protected ?string $heading = 'Yearly sales';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ReportsCluster::canAccess();
    }

    public function getDescription(): string
    {
        $total = array_sum($this->getCachedData()['datasets'][0]['data']);

        return 'All-time total: '.Number::currency($total, in: Filament::getTenant()->currency_code ?? 'USD');
    }

    protected function getData(): array
    {
        $totals = app(SalesReportService::class)->yearlyTotals(Filament::getTenant());

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_values($totals),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#36A2EB',
                ],
            ],
            'labels' => array_map('strval', array_keys($totals)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
