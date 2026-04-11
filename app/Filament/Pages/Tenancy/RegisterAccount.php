<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\AccountRole;
use App\Filament\Common\Schemas\AccountProfileForm;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RegisterAccount extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create Account';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AccountProfileForm::components());
    }

    protected function handleRegistration(array $data): Model
    {
        $user = Auth::user();

        $account = $this->getModel()::create([
            'name' => $data['name'],
            'owner_id' => $user->getKey(),
            'address' => $data['address'] ?? null,
            'email' => $data['email'] ?? null,
        ]);

        $account->users()->attach($user->getKey(), [
            'role' => AccountRole::Owner,
        ]);

        return $account;
    }
}
