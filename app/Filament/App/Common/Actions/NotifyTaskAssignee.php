<?php

namespace App\Filament\App\Common\Actions;

use App\Filament\App\Resources\Tasks\Pages\EditTask;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class NotifyTaskAssignee
{

    public static function handle(Task $task): void
    {
        Notification::make('notifyAssignee')
            ->title('Task Created')
            ->body("Task '{$task->title}' has been created and assigned to you.")
            ->success()
            ->actions([
                Action::make('viewTask')
                    ->url(EditTask::getUrl(['tenant' => Filament::getTenant(), 'record' => $task])),
            ])
            ->sendToDatabase($task->assignee);
    }
}
