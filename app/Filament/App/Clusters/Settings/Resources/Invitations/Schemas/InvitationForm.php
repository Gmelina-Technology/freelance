<?php

namespace App\Filament\App\Clusters\Settings\Resources\Invitations\Schemas;

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

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
                    ->options(fn (): array => static::assignableRoleOptions())
                    ->required(),
            ]);
    }

    /**
     * Only owners may invite someone as an owner.
     *
     * @return array<string, string>
     */
    protected static function assignableRoleOptions(): array
    {
        $canGrantOwner = Auth::user()->can(AccountCapability::ManageOwners, Filament::getTenant());

        return $canGrantOwner ? AccountRole::options() : Arr::except(AccountRole::options(), [AccountRole::Owner->value]);
    }
}
