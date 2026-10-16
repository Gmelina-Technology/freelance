<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountRole;
use App\Services\BusinessEventLogger;
use Database\Factories\UserFactory;
use DateTimeInterface;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasEmailAuthentication, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use InteractsWithEmailAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'owner_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->using(AccountUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->accounts;
    }

    /**
     * Issue a personal access token that only works on the given account. A null expiry never expires.
     *
     * @param  array<int, string>  $abilities
     */
    public function createAccountToken(Account $account, string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $token = $this->createToken($name, $abilities, $expiresAt);

        $token->accessToken->forceFill(['account_id' => $account->getKey()])->save();

        app(BusinessEventLogger::class)->record('api_token.created', $account->getKey(), [
            'token_id' => $token->accessToken->getKey(),
            'abilities' => implode(',', $abilities),
            'expires_at' => $expiresAt?->format(DATE_ATOM),
        ]);

        return $token;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->accounts()->whereKey($tenant->getKey())->exists() || $this->ownedAccounts()->whereKey($tenant->getKey())->exists();
    }

    public function getAccountRole(Account $account): ?AccountRole
    {
        if ((int) $account->owner_id === (int) $this->getKey()) {
            return AccountRole::Owner;
        }

        $pivot = $this->accounts->where('id', $account->getKey())->first()?->pivot;

        return $pivot?->role;
    }

    /**
     * Role on the account with this id, read from the accounts / ownedAccounts relations loaded once on
     * this instance. The owner_id rule wins, matching getAccountRole(). Null when the user is not in the account.
     */
    public function getAccountRoleById(int $accountId): ?AccountRole
    {
        $this->loadMissing(['accounts', 'ownedAccounts']);

        if ($this->ownedAccounts->contains('id', $accountId)) {
            return AccountRole::Owner;
        }

        return $this->accounts->firstWhere('id', $accountId)?->pivot?->role;
    }

    public function hasAccountRole(Account $account, AccountRole|string $role): bool
    {
        $roleValue = $role instanceof AccountRole ? $role : AccountRole::from($role);

        return $this->getAccountRole($account) === $roleValue;
    }

    public function isOwner(Account $account): bool
    {
        return $this->hasAccountRole($account, AccountRole::Owner);
    }

    public function isManager(Account $account): bool
    {
        return $this->hasAccountRole($account, AccountRole::Manager);
    }

    public function isMember(Account $account): bool
    {
        return $this->hasAccountRole($account, AccountRole::Member);
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->accounts()->first();
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function commentReactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'app') {
            return true;
        }

        return $this->isPlatformAdmin() && $this->hasEmailAuthentication();
    }

    /**
     * Whether the email is on the ADMIN_EMAILS allowlist. Tolerates a null, empty, string or array config,
     * trims each entry and ignores case.
     */
    public function isPlatformAdmin(): bool
    {
        return collect(Arr::wrap(config('app.admin_emails')))
            ->flatMap(fn ($value): array => explode(',', (string) $value))
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->contains(Str::lower(trim((string) $this->email)));
    }
}
