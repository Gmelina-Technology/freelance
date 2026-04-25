<?php

namespace App\Filament\App\Clusters\Settings\Resources\Invitations\Schemas;

use App\Enums\AccountRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->options(AccountRole::class)
                    ->required(),
            ]);
    }
}
