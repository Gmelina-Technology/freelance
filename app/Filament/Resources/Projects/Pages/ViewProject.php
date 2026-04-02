<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_tasks')
                ->label('View Tasks')
                ->icon(Heroicon::ListBullet)
                ->url($this->getResource()::getUrl('tasks', ['record' => $this->record])),
            EditAction::make(),
        ];
    }
}
