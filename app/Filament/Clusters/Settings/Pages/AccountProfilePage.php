<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Common\Schemas\AccountProfileForm;
use App\Models\Account;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AccountProfilePage extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    protected static ?string $navigationLabel = 'Account Profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserPlus;

    protected string $view = 'filament.clusters.settings.pages.account-profile-page';

    protected static ?string $cluster = SettingsCluster::class;

    public function mount(): void
    {
        $account = Filament::getTenant();
        $this->form->fill([
            'name' => $account->name,
            'email' => $account->email,
            'address' => $account->address,
            'default_bank_detail_id' => $account->default_bank_detail_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Account::class)
            ->components([
                Section::make('Profile Information')
                    ->schema([
                        ...AccountProfileForm::components(),
                        Select::make('default_bank_detail_id')
                            ->relationship('bankDetails', 'bank_name')
                            ->preload()
                            ->searchable(),
                    ])
                    ->footerActions([
                        Action::make('update_profile')
                            ->label('Update Profile')
                            ->action('updateProfile'),
                    ]),
            ])->statePath('data');
    }

    public function updateProfile(): void
    {
        $data = $this->form->getState();

        $account = Filament::getTenant();

        $account->update($data);

        Notification::make()
            ->title('Profile Updated')
            ->body('Your account profile has been updated.')
            ->success()
            ->send();
    }
}
