<?php

namespace App\Traits;

use App\Models\Account;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAccount
{
    public function bootHasAccount(): void
    {
        static::creating(function ($model) {
            if (Filament::hasTenancy() && empty($model->account_id)) {
                $model->account_id = Filament::getTenant()?->getKey() ?? null;
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
