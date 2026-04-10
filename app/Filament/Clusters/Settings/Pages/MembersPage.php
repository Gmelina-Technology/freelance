<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Enums\AccountRole;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MembersPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.clusters.settings.pages.members-page';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $title = 'Members';

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Filament::getTenant()->users())
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('role')->label('Role')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('change_role')
                    ->label('Change Role')
                    ->visible(fn(User $record) => $record->role !== AccountRole::Owner->value)
                    ->fillForm(fn(User $record) => [
                        'role' => $record->role,
                    ])
                    ->schema([
                        Select::make('role')->options(AccountRole::class),
                    ])
                    ->action(function (array $data, User $record) {

                        $record->accounts()->syncWithoutDetaching([
                            Filament::getTenant()->id => ['role' => $data['role']],
                        ]);

                        Notification::make()
                            ->title('Role Updated')
                            ->body("The user's role has been updated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                //
            ]);
    }
}
