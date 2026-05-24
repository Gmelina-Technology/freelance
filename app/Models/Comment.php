<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'body',
        'body_format',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->with(['author', 'attachments', 'reactions.user', 'replies'])
            ->oldest();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommentAttachment::class);
    }

    /**
     * Scope to eager-load all nested relationships needed for display.
     * Prevents N+1 queries when rendering comments with replies, reactions, and attachments.
     */
    public function scopeWithNestedReplies($query)
    {
        return $query->with([
            'author',
            'attachments',
            'reactions.user',
            'replies',
        ]);
    }
}
