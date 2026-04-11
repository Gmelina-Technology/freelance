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
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'role' => AccountRole::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (blank($invitation->token)) {
                $invitation->token = Str::random(40);
            }

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
