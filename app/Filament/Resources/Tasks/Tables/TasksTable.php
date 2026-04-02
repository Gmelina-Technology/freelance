<?php

namespace App\Filament\Resources\Tasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->when(
                Auth::user()->isMember(Filament::getTenant()),
                function (Builder $query) {
                    $query->where('assigned_user_id', Auth::id());
                }
            ))
            ->columns([
                TextColumn::make('client.name')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->preload(),
                SelectFilter::make('assignee_id')
                    ->relationship('assignee', 'name')
                    ->preload()
                    ->visible(fn() => !Auth::user()->isMember(Filament::getTenant())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
