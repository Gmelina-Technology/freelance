<?php

namespace App\Filament\App\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Support\Facades\Auth;

class MyTasksTable extends BaseTableWidget
{
    protected static ?int $sort = 8;

    protected static ?string $heading = 'My Tasks';

    protected static ?int $defaultPaginationPageOption = 5;

    public function table(Table $table): Table
    {
        $accountId = Filament::getTenant()->id;
        $userId = Auth::id();

        return $table
            ->header(null)

            ->query(
                fn () => Task::where('account_id', $accountId)
                    ->where('assigned_user_id', $userId)
                    ->whereNot('status', TaskStatus::COMPLETED->value)
                    ->latest('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'info' => TaskStatus::OPEN,
                        'warning' => TaskStatus::IN_PROGRESS,
                        'secondary' => TaskStatus::REVIEW,
                    ])
                    ->formatStateUsing(fn (TaskStatus $state): string => $state->getLabel())
                    ->sortable(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10, 25]);
    }
}
