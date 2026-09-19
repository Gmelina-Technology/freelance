<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Traits\HasAccount;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'project_id',
        'client_id',
        'task_id',
        'number',
        'invoice_ref',
        'amount',
        'status',
        'issued_at',
        'valid_until',
        'accepted_at',
        'notes',
        'email_content',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => QuoteStatus::class,
            'issued_at' => 'datetime',
            'valid_until' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, QuoteItem::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === QuoteStatus::Accepted;
    }

    public function recalculateAmount(): void
    {
        $this->update([
            'amount' => $this->items()->get()->sum(fn (QuoteItem $item): float => (float) $item->quantity * (float) $item->unit_price),
        ]);
    }
}
