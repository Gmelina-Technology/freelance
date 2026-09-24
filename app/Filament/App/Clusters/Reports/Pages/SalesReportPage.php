<?php

namespace App\Filament\App\Clusters\Reports\Pages;

use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Filament\App\Clusters\Reports\Widgets\SalesByMonthChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByQuarterChart;
use App\Filament\App\Clusters\Reports\Widgets\SalesByYearChart;
use Filament\Pages\Page;

class SalesReportPage extends Page
{
    protected static ?string $cluster = ReportsCluster::class;

    protected static ?string $slug = 'sales';

    protected static ?string $navigationLabel = 'Sales over time';

    protected static ?string $title = 'Sales over time';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return ReportsCluster::canAccess();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SalesByMonthChart::class,
            SalesByQuarterChart::class,
            SalesByYearChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
