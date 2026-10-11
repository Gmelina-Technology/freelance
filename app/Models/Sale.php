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

/**
 * A sale is recorded in its original currency (currency_code) and counts in reports once it is
 * converted into the account currency. converted_at, converted_currency_code, converted_amount and
 * gross_amount are either all null (unconverted) or all set (converted). SaleConversionService
 * keeps them that way.
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
        'converted_currency_code',
        'converted_amount',
        'fee_amount',
        'tax_amount',
        'gross_amount',
        'exchange_rate',
        'conversion_note',
        'converted_at',
        'converted_by',
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

    public function convertedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'converted_currency_code', 'code');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->whereNotNull($query->getModel()->qualifyColumn('converted_at'));
    }

    public function scopeUnconverted(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('converted_at'));
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
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
