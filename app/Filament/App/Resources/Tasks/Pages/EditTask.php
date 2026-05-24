<?php

namespace App\Filament\App\Resources\Tasks\Pages;

use App\Filament\App\Resources\Tasks\TaskResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Auth;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_worklog')
                ->label('Add Worklog')
                ->schema([
                    Group::make([
                        DateTimePicker::make('worked_date')
                            ->label('Worked Date')
                            ->required(),
                        TextInput::make('hours')
                            ->label('Hours')
                            ->numeric()
                            ->required(),
                    ])->columns(2),
                    Textarea::make('description')
                        ->label('Description'),
                ])
                ->action(function ($record, array $data) {
                    $record->workLogs()->create([
                        'account_id' => $record->account_id,
                        'user_id' => Auth::id(),
                        'worked_date' => $data['worked_date'],
                        'hours' => $data['hours'],
                        'description' => $data['description'] ?? null,
                    ]);
                }),
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
