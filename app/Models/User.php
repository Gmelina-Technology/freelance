<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountRole;
use Database\Factories\UserFactory;
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
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasEmailAuthentication, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->accounts()->whereKey($tenant->getKey())->exists() || $this->ownedAccounts()->whereKey($tenant->getKey())->exists();
    }

    public function getAccountRole(Account $account): ?AccountRole
    {
        $pivot = $this->accounts->where('id', $account->getKey())->first()?->pivot;

        return $pivot?->role;
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

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'app') {
            return true;
        }

        return in_array($this->email, explode(',', config('app.admin_emails')));
    }
}
