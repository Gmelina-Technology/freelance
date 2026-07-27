<?php

namespace App\Filament\App\Widgets;

use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlySalesChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Sales';

    protected static ?int $sort = 2;

    public function getColumns(): int | array
    {
        return  Auth::user()->isMember(Filament::getTenant()) ? 3 : 12;
    }

    public static function canView(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }

    protected function getData(): array
    {
        $salesData = [];
        $labels = [];

        for ($month = 1; $month <= 12; $month++) {

            $total = Sale::query()
                ->where('account_id', Filament::getTenant()->id)
                ->whereYear('transaction_date', now()->year)
                ->whereMonth('transaction_date', $month)
                ->sum('amount');

            $salesData[] = $total;

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
