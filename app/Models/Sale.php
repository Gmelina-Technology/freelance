<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasAccount;

    protected $fillable = [
        'account_id',
        'category',
        'transaction_date',
        'amount',
        'reference_key'
    ];

    /**
     * Get the invoice that owns the Sale
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_key', 'number');
    }
}
