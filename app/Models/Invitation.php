<?php

namespace App\Models;

use App\Enums\AccountRole;
use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'email',
        'role',
        'token',
        'invited_by_id',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'role' => AccountRole::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (blank($invitation->token)) {
                $invitation->token = Str::random(40);
            }

            $invitation->expires_at ??= now()->addDays(config('invitations.expiry_days'));

            if (blank($invitation->invited_by_id) && Auth::check()) {
                $invitation->invited_by_id = Auth::id();
            }
        });
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    /**
     * Whether the invitation ran past its expiry. A null expiry never expires.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return ! $this->isPending();
    }

    public function accept(): bool
    {
        if ($this->isAccepted()) {
            return false;
        }

        $this->accepted_at = now();

        return $this->save();
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->role->getLabel();
    }
}
