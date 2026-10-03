<?php

namespace App\Filament\App\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

/**
 * Self-service Sanctum personal access tokens for the signed-in user, used by
 * external MCP clients (/mcp) and the REST API (/api). Each token is bound to the
 * tenant account it was created in and can only act on that account.
 */
class ApiTokens extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * Abilities a token can carry. A token with none is read-only: BaseTool::requireAbility
     * only passes for the specific ability or '*', so an empty list never grants writes.
     *
     * @var array<string, string>
     */
    public const ABILITIES = [
        'tasks:write' => 'Tasks: create and update',
        'quotes:write' => 'Quotes: create and update',
        'invoices:write' => 'Invoices: create and update',
    ];

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'API Tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'api-tokens';

    protected string $view = 'filament.pages.api-tokens';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PersonalAccessToken::query()
                ->whereMorphedTo('tokenable', Auth::user())
                ->where('account_id', Filament::getTenant()->getKey())
                ->latest())
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('account')
                    ->state(fn () => Filament::getTenant()->name),
                TextColumn::make('abilities')
                    ->badge()
                    ->placeholder('Read-only'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->dateTimeTooltip(),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke token')
                    ->modalDescription('Any client using this token will immediately lose access.')
                    ->action(fn (PersonalAccessToken $record) => $record->delete()),
            ])
            ->emptyStateHeading('No API tokens yet')
            ->emptyStateDescription('Create a token for this account to connect an MCP client or call the REST API.');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Create token')
                ->icon(Heroicon::Plus)
                ->modalHeading('Create API token')
                ->modalSubmitActionLabel('Create')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            table: 'personal_access_tokens',
                            column: 'name',
                            modifyRuleUsing: fn (Unique $rule) => $rule
                                ->where('tokenable_type', (new User)->getMorphClass())
                                ->where('tokenable_id', Auth::id())
                                ->where('account_id', Filament::getTenant()->getKey()),
                        ),
                    CheckboxList::make('abilities')
                        ->options(self::ABILITIES)
                        ->default(array_keys(self::ABILITIES))
                        ->helperText('Leave everything unchecked for a read-only token.'),
                ])
                ->action(function (array $data): void {
                    $abilities = array_values(array_intersect(array_keys(self::ABILITIES), $data['abilities'] ?? []));

                    $user = Auth::user();
                    $account = Filament::getTenant();

                    abort_unless($user->canAccessTenant($account), 403);

                    $token = $user->createAccountToken($account, $data['name'], $abilities);

                    $this->replaceMountedAction('revealToken', ['token' => $token->plainTextToken]);
                }),
        ];
    }

    /**
     * Shown right after creation. The plaintext token only lives in this modal's
     * arguments; it is never stored or logged.
     */
    public function revealTokenAction(): Action
    {
        return Action::make('revealToken')
            ->modalHeading('Your new API token')
            ->modalWidth('2xl')
            ->fillForm(fn (array $arguments): array => ['token' => $arguments['token'] ?? ''])
            ->schema([
                TextInput::make('token')
                    ->label('API token')
                    ->helperText("Copy this token now. You won't see it again.")
                    ->readOnly()
                    ->dehydrated(false)
                    ->copyable(copyMessage: 'Copied!', copyMessageDuration: 1500),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->closeModalByClickingAway(false);
    }

    public function getMcpUrl(): string
    {
        return url('/mcp');
    }
}
