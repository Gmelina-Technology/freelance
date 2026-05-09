<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Enums\AccountRole;
use App\Enums\EmailTemplateType;
use App\Filament\App\Common\Schemas\AccountProfileForm;
use App\Models\EmailTemplate;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            $account = $this->getModel()::create([
                'name' => $data['name'],
                'owner_id' => $user->getKey(),
                'address' => $data['address'] ?? null,
                'email' => $data['email'] ?? null,
                'logo' => $data['logo'] ?? null,
            ]);

            EmailTemplate::create([
                'account_id' => $account->id,
                'type' => EmailTemplateType::INVOICE_REQUEST,
                'subject' => 'Task Service Invoice',
                'body' => config('email-templates.defaults.invoice.body'),
            ]);

            $account->users()->attach($user->getKey(), [
                'role' => AccountRole::Owner,
            ]);

            return $account;
        });
    }
}
