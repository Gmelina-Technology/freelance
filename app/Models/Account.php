<?php

namespace App\Models;

use App\Enums\AccountRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'address',
        'email',
        'default_bank_detail_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AccountUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->users();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function bankDetails(): HasMany
    {
        return $this->hasMany(BankDetail::class);
    }

    public function defaultBankDetail(): BelongsTo
    {
        return $this->belongsTo(BankDetail::class, 'default_bank_detail_id');
    }

    public function getUserRole(User $user): ?AccountRole
    {
        $pivot = $this->users()->where('user_id', $user->getKey())->first()?->pivot;

        return $pivot?->role;
    }

    public function hasUserRole(User $user, AccountRole|string $role): bool
    {
        $roleValue = $role instanceof AccountRole ? $role : AccountRole::from($role);

        return $this->getUserRole($user) === $roleValue;
    }

    public function hasOwner(User $user): bool
    {
        return $this->hasUserRole($user, AccountRole::Owner);
    }

    public function hasManager(User $user): bool
    {
        return $this->hasUserRole($user, AccountRole::Manager);
    }
}
