<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class RevenueStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return Auth::user()->isOwner(Filament::getTenant());
    }

    protected function getStats(): array
    {
        $accountId = Filament::getTenant()->id;

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        $completedThisMonth = Project::where('account_id', $accountId)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$currentMonthStart, $currentMonthEnd])
            ->count();

        // Placeholder for actual revenue tracking (would need revenue field in projects)
        $estimatedRevenue = $completedThisMonth * 500; // Assuming $500 per project average

        return [
            Stat::make('Monthly Revenue', '$'.number_format($estimatedRevenue))
                ->description('This month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Completed This Month', $completedThisMonth)
                ->description('Projects finished')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
