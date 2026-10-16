<?php

namespace App\Filament\App\Clusters\Settings\Resources\Members;

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use App\Filament\App\Clusters\Settings\Resources\Members\Pages\ManageMembers;
use App\Filament\App\Clusters\Settings\SettingsCluster;
use App\Models\User;
use App\Services\AccountMemberService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Members';

    protected static ?string $navigationLabel = 'Members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    protected static ?string $tenantOwnershipRelationshipName = 'accounts';

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('accounts'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn (User $record) => $record->getAccountRole(Filament::getTenant())),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change Role')
                    ->visible(fn (User $record): bool => static::canChangeRoleOf($record))
                    ->authorize(fn (User $record): bool => static::canChangeRoleOf($record))
                    ->fillForm(fn (User $record) => [
                        'role' => $record->getAccountRole(Filament::getTenant()),
                    ])
                    ->schema([
                        Select::make('role')
                            ->options(fn (): array => static::assignableRoleOptions())
                            ->required(),
                    ])
                    ->action(function (array $data, User $record, AccountMemberService $members): void {
                        $members->changeRole(
                            Filament::getTenant(),
                            Auth::user(),
                            $record,
                            AccountRole::from($data['role']),
                        );

                        Notification::make()
                            ->title('Role Updated')
                            ->body("The user's role has been updated.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * The account owner's own role is fixed, and only owners may touch another owner.
     */
    protected static function canChangeRoleOf(User $record): bool
    {
        $account = Filament::getTenant();

        if ((int) $account->owner_id === (int) $record->getKey()) {
            return false;
        }

        $current = $record->getAccountRole($account);

        return $current !== null
            && Auth::user()->can(AccountCapability::forRoleChange($current, $current), $account);
    }

    /**
     * @return array<string, string>
     */
    protected static function assignableRoleOptions(): array
    {
        $canGrantOwner = Auth::user()->can(AccountCapability::ManageOwners, Filament::getTenant());

        return $canGrantOwner ? AccountRole::options() : Arr::except(AccountRole::options(), [AccountRole::Owner->value]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMembers::route('/'),
        ];
    }
}
