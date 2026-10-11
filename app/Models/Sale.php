<?php

namespace App\Models;

use App\Enums\SaleConversionStatus;
use App\Traits\HasAccount;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A sale is recorded in its original currency (currency_code) and starts unconverted. It counts in
 * reports once a user converts it into the account currency, which creates its SaleConversion.
 * See SaleConversionService.
 */
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
        'currency_code',
    ];

    /**
     * Get the invoice that owns the Sale
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_key', 'number');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function saleConversion(): HasOne
    {
        return $this->hasOne(SaleConversion::class);
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->has('saleConversion');
    }

    public function scopeUnconverted(Builder $query): Builder
    {
        return $query->doesntHave('saleConversion');
    }

    public function isConverted(): bool
    {
        return $this->saleConversion !== null;
    }

    /**
     * @return Attribute<SaleConversionStatus, never>
     */
    protected function conversionStatus(): Attribute
    {
        return Attribute::get(fn (): SaleConversionStatus => $this->isConverted()
            ? SaleConversionStatus::Converted
            : SaleConversionStatus::Unconverted);
    }
}
