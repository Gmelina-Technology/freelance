<?php

namespace App\Filament\App\Clusters\Settings\Resources\Invitations\Pages;

use App\Enums\AccountRole;
use App\Filament\App\Clusters\Settings\Resources\Invitations\InvitationResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): Invitation => app(InvitationService::class)->invite(
                    Filament::getTenant(),
                    Auth::user(),
                    $data['email'],
                    AccountRole::from($data['role']),
                )),
        ];
    }
}
