<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Commentable
{
    public function comments(): MorphMany;

    public function rootComments(): MorphMany;

    public function getCommentAccountId(): int;

    public function isCommentVisibleTo(User $user): bool;
}
