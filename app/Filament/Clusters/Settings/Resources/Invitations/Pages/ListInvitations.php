<?php

namespace App\Filament\Clusters\Settings\Resources\Invitations\Pages;

use App\Filament\Clusters\Settings\Resources\Invitations\InvitationResource;
use App\Mail\InvitationEmail;
use App\Models\Invitation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Mail;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function (Invitation $record): void {
                    Mail::to($record->email)->send(new InvitationEmail($record));
                }),
        ];
    }
}
