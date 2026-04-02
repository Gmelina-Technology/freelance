<?php

namespace App\Filament\Clusters\Settings\Resources\Invitations\Pages;

use App\Filament\Clusters\Settings\Resources\Invitations\InvitationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvitation extends EditRecord
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
