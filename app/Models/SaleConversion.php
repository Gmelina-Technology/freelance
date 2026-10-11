<?php

namespace App\Models;

use Database\Factories\SaleConversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sale's conversion into its account currency. Only the user-entered figures are fillable;
 * the sale, currency, computed figures and audit columns are set by SaveSaleConversionAction.
 */
class SaleConversion extends Model
{
    /** @use HasFactory<SaleConversionFactory> */
    use HasFactory;

    /**
     * The largest value a decimal(15, 2) money column holds.
     */
    public const MAX_AMOUNT = '9999999999999.99';

    protected $fillable = [
        'converted_amount',
        'fee_amount',
        'tax_amount',
        'conversion_note',
    ];

    protected function casts(): array
    {
        return [
            'converted_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'converted_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function convertedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'converted_currency_code', 'code');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }
}
