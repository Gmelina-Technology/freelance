<?php

namespace App\Filament\App\Resources\Tasks\Tables;

use App\Enums\TaskStatus;
use App\Filament\App\Resources\Tasks\Pages\EditTask;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

use function Livewire\str;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->when(
                    Auth::user()->isMember(Filament::getTenant()),
                    function (Builder $query) {
                        $query->where('assigned_user_id', Auth::id());
                    }
                );
            })
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('due_date')
                    ->formatStateUsing(fn($state) => str($state?->diffForHumans([
                        'options' => Carbon::JUST_NOW | Carbon::ONE_DAY_WORDS | Carbon::TWO_DAY_WORDS,
                        'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                        'parts' => 1
                    ]))->title())
                    ->dateTimeTooltip('M d, Y h:m a')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('project.name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->preload(),
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name')
                    ->preload(),
                SelectFilter::make('assignee_id')
                    ->label('Assignee')
                    ->relationship('assignee', 'name', function (Builder $query) {
                        $query->whereHas('accounts', function ($subQuery) {
                            $subQuery->whereKey(Filament::getTenant()->id);
                        });
                    })
                    ->preload()
                    ->visible(fn() => ! Auth::user()->isMember(Filament::getTenant())),
            ])
            ->filtersFormColumns(3)
            ->columnManagerColumns(3)
            ->groups([
                Group::make('client.name')
                    ->collapsible()
                    ->label('Client'),
                Group::make('project.name')
                    ->collapsible()
                    ->label('Project'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconSize(IconSize::Small)
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->iconSize(IconSize::Small)
            ])
            ->toolbarActions([
                BulkAction::make('updateStatus')
                    ->requiresConfirmation()
                    ->schema([
                        Select::make('status')
                            ->required()
                            ->options(TaskStatus::class)
                    ])->action(function (Collection $records, array $data) {
                        $status = $data['status'];
                        $records->each->update([
                            'status' => $data['status']
                        ]);

                        Notification::make('updateStatus')
                            ->success()
                            ->title('Bulk Update Status')
                            ->body('Select records updated status successfully to - ' . $status->getLabel())
                            ->send();
                    })
            ]);
    }
}
