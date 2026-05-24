<?php

namespace App\Traits;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function rootComments(): MorphMany
    {
        return $this->comments()
            ->whereNull('parent_id')
            ->latest();
    }

    public function getCommentAccountId(): int
    {
        return (int) $this->account_id;
    }
}
