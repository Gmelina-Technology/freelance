<?php

namespace App\Models;

use App\Traits\HasAccount;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'category',
        'transaction_date',
        'amount',
        'reference_key',
    ];

    /**
     * Get the invoice that owns the Sale
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_key', 'number');
    }
}
