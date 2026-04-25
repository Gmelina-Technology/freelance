<?php

namespace App\Filament\App\Resources\Tasks\Pages;

use App\Filament\App\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;
}
