<?php

namespace App\Filament\App\Resources\Projects\Pages;

use App\Filament\App\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(! Auth::user()->isMember(Filament::getTenant())),
        ];
    }
}
