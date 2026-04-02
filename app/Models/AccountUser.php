<?php

namespace App\Models;

use App\Enums\AccountRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AccountUser extends Pivot
{
    protected $fillable = [
        'account_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => AccountRole::class,
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === AccountRole::Owner;
    }

    public function isManager(): bool
    {
        return $this->role === AccountRole::Manager;
    }

    public function isMember(): bool
    {
        return $this->role === AccountRole::Member;
    }
}
