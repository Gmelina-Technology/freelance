<?php

namespace App\Filament\App\Widgets;

use App\Services\Reports\SalesReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlySalesChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Sales';

    protected static ?int $sort = 2;

    public function getColumns(): int|array
    {
        return Auth::user()->isMember(Filament::getTenant()) ? 3 : 12;
    }

    public static function canView(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }

    protected function getData(): array
    {
        $totals = app(SalesReportService::class)->monthlyTotals(Filament::getTenant(), now()->year);

        $salesData = array_values($totals);
        $labels = [];

        for ($month = 1; $month <= 12; $month++) {
            $labels[] = date('M', mktime(0, 0, 0, $month, 1));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $salesData,
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#36A2EB',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
