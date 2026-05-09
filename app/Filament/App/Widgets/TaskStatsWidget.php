<?php

namespace App\Filament\App\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $accountId = Filament::getTenant()->id;

        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisWeekEnd = Carbon::now()->endOfWeek();

        $pendingTasks = Task::where('account_id', $accountId)
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->count();

        $tasksThisWeek = Task::where('account_id', $accountId)
            ->whereBetween('due_date', [$thisWeekStart, $thisWeekEnd])
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->count();

        $overdueTasks = Task::where('account_id', $accountId)
            ->where('due_date', '<', Carbon::now())
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->count();

        return [
            Stat::make('Overdue Tasks', $overdueTasks)
                ->description('Needs attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->visible($overdueTasks > 0),
            Stat::make('Tasks This Week', $tasksThisWeek)
                ->description('Due this week')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
            Stat::make('Pending Tasks', $pendingTasks)
                ->description('Open & In Progress')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('warning'),
        ];
    }
}
