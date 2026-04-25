<?php

namespace App\Filament\App\Resources\Tasks\Pages;

use App\Enums\TaskPriority;
use App\Filament\App\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'urgent' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('priority', TaskPriority::High)),
            'due_today' => Tab::make('Due Today')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('due_date', now())),
        ];
    }
}
