<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'name',
        'description',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'status' => ProjectStatus::class,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Members only see projects they are assigned to, mirroring the projects list.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Account $account): void
    {
        if ($user->isMember($account)) {
            $query->whereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->getKey()));
        }
    }
}
