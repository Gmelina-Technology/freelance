<?php

namespace App\Filament\App\Clusters\Settings\Resources\Members;

use App\Enums\AccountRole;
use App\Filament\App\Clusters\Settings\Resources\Members\Pages\ManageMembers;
use App\Filament\App\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Members';

    protected static ?string $navigationLabel = 'Members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $tenantOwnershipRelationshipName = 'accounts';

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn($query) => $query->with('accounts'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn(User $record) =>  $record->getAccountRole(Filament::getTenant())),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change Role')
                    ->visible(fn(User $record) => $record->role !== AccountRole::Owner->value)
                    ->fillForm(fn(User $record) => [
                        'role' => $record->getAccountRole(Filament::getTenant()),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMembers::route('/'),
        ];
    }
}
