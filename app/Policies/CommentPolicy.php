<?php

namespace App\Policies;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Cache for account membership checks [userId => [accountId => bool]]
     *
     * @var array<int, array<int, bool>>
     */
    private static array $accountMembershipCache = [];

    public function view(User $user, Comment $comment): bool
    {
        return $this->belongsToAccount($user, $comment->account_id);
    }

    public function create(User $user, Commentable $commentable): bool
    {
        return $this->belongsToAccount($user, $commentable->getCommentAccountId());
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $this->canModerate($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $this->canModerate($user, $comment);
    }

    public function reply(User $user, Comment $comment): bool
    {
        if (! config('comments.allow_self_reply') && $comment->user_id === $user->id) {
            return false;
        }

        return $this->view($user, $comment);
    }

    public function react(User $user, Comment $comment): bool
    {
        return $this->view($user, $comment);
    }

    private function canModerate(User $user, Comment $comment): bool
    {
        return $user->accounts()
            ->whereKey($comment->account_id)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists()
            || $user->ownedAccounts()->whereKey($comment->account_id)->exists();
    }

    private function belongsToAccount(User $user, int $accountId): bool
    {
        $userId = $user->id;

        // Check cache first to avoid duplicate queries in the same request
        if (isset(self::$accountMembershipCache[$userId][$accountId])) {
            return self::$accountMembershipCache[$userId][$accountId];
        }

        $result = $user->accounts()->whereKey($accountId)->exists()
            || $user->ownedAccounts()->whereKey($accountId)->exists();

        // Cache the result
        self::$accountMembershipCache[$userId][$accountId] = $result;

        return $result;
    }
}
