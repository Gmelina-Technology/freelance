<?php

namespace App\Filament\App\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getTermsFormComponent(),
            ]);
    }

    protected function getTermsFormComponent(): Component
    {
        return Checkbox::make('terms')
            ->label(new HtmlString(
                'I agree to the <a href="'.e(route('terms')).'" target="_blank" class="text-primary-600 underline">Terms of Service</a>'
                .' and have read the <a href="'.e(route('privacy')).'" target="_blank" class="text-primary-600 underline">Privacy Policy</a>.'
            ))
            ->accepted()
            ->validationMessages([
                'accepted' => 'You must accept the Terms of Service and Privacy Policy to create an account.',
            ])
            ->dehydrated(false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        $data['terms_accepted_at'] = now();

        return parent::handleRegistration($data);
    }
}
