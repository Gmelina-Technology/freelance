<?php

namespace App\Filament\App\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TasksByTeamChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Team Workload';

    protected static ?int $contentHeight = 300;

    public static function canView(): bool
    {
        return Auth::user()->isOwner(Filament::getTenant());
    }

    protected function getData(): array
    {
        $accountId = Filament::getTenant()->id;

        $taskCounts = Task::where('account_id', $accountId)
            ->whereIn('status', [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])
            ->toBase()
            ->selectRaw('assigned_user_id, COUNT(*) as aggregate')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate', 'assigned_user_id');

        $assigneeNames = User::whereIn('id', $taskCounts->keys()->filter()->all())->pluck('name', 'id');

        $labels = [];
        $data = [];

        foreach ($taskCounts as $userId => $count) {
            $labels[] = $assigneeNames[$userId] ?? 'Unassigned';
            $data[] = (int) $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => $data,
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
