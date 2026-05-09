<?php

namespace App\Filament\App\Clusters\Settings\Resources\Members\Pages;

use App\Filament\App\Clusters\Settings\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMembers extends ManageRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
