<?php

namespace App\Policies;

use App\Contracts\Commentable;
use App\Enums\AccountRole;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        return $this->belongsToAccount($user, $comment->account_id)
            && $this->commentableVisible($user, $comment);
    }

    public function create(User $user, Commentable $commentable): bool
    {
        return $this->belongsToAccount($user, $commentable->getCommentAccountId())
            && $commentable->isCommentVisibleTo($user);
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

    private function commentableVisible(User $user, Comment $comment): bool
    {
        $commentable = $comment->loadMissing('commentable')->commentable;

        return $commentable instanceof Commentable && $commentable->isCommentVisibleTo($user);
    }

    private function canModerate(User $user, Comment $comment): bool
    {
        return in_array($user->getAccountRoleById($comment->account_id), [AccountRole::Owner, AccountRole::Manager], true);
    }

    private function belongsToAccount(User $user, int $accountId): bool
    {
        return $user->getAccountRoleById($accountId) !== null;
    }
}
