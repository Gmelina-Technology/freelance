<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class ActiveProjectsTable extends BaseTableWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Active Projects';

    protected static ?int $defaultPaginationPageOption = 5;

    public function table(Table $table): Table
    {
        $accountId = Filament::getTenant()->id;

        return $table
            ->query(
                fn() => Project::where('account_id', $accountId)
                    ->where('status', '!=', 'completed')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'info' => 'open',
                        'warning' => 'in_progress',
                        'success' => 'on_track',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10, 25]);
    }
}
