<?php

namespace App\Filament\App\Widgets;

use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 2;

    protected function getStats(): array
    {
        $accountId = Filament::getTenant()->id;

        $activeProjects = Project::where('account_id', $accountId)
            ->whereIn('status', ['in_progress', 'on_track'])
            ->count();

        $completedProjects = Project::where('account_id', $accountId)
            ->where('status', 'completed')
            ->count();

        return [
            Stat::make('Active Projects', $activeProjects)
                ->description('Currently in progress')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Completed Projects', $completedProjects)
                ->description('Total completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
