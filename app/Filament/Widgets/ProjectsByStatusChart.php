<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class ProjectsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Projects by Status';

    protected static ?int $contentHeight = 300;

    protected function getData(): array
    {
        $accountId = Filament::getTenant()->id;

        $statuses = Project::where('account_id', $accountId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'datasets' => [
                [
                    'label' => 'Projects',
                    'data' => $statuses->values()->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', // blue
                        '#10b981', // green
                        '#f59e0b', // amber
                        '#ef4444', // red
                    ],
                    'borderColor' => '#fff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $statuses->keys()->map(function ($status) {
                return ucfirst(str_replace('_', ' ', $status));
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
