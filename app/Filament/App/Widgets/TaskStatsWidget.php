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

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $accountId = Filament::getTenant()->id;

        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisWeekEnd = Carbon::now()->endOfWeek();

        $now = Carbon::now();

        $counts = Task::where('account_id', $accountId)
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->toBase()
            ->selectRaw(
                'COUNT(*) as pending, '
                .'COALESCE(SUM(CASE WHEN due_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as this_week, '
                .'COALESCE(SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END), 0) as overdue',
                [
                    $thisWeekStart->toDateTimeString(),
                    $thisWeekEnd->toDateTimeString(),
                    $now->toDateTimeString(),
                ]
            )
            ->first();

        $pendingTasks = (int) $counts->pending;
        $tasksThisWeek = (int) $counts->this_week;
        $overdueTasks = (int) $counts->overdue;

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
