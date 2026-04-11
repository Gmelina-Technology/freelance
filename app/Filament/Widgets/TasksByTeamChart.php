<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class TasksByTeamChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Team Workload';

    protected static ?int $contentHeight = 300;

    protected function getData(): array
    {
        $accountId = Filament::getTenant()->id;

        $tasks = Task::where('account_id', $accountId)
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->with('assignee')
            ->get()
            ->groupBy('assigned_user_id')
            ->map(function ($group) {
                return $group->count();
            });

        $assigneeNames = Task::where('account_id', $accountId)
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->distinct('assigned_user_id')
            ->with('assignee')
            ->get()
            ->pluck('assignee.name', 'assigned_user_id');

        $labels = [];
        $data = [];

        foreach ($tasks as $userId => $count) {
            $labels[] = $assigneeNames[$userId] ?? 'Unassigned';
            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#1e40af',
                    'borderWidth' => 1,
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
