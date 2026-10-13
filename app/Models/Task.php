<?php

namespace App\Models;

use App\Contracts\Commentable;
use App\Enums\TaskBillingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Traits\HasAccount;
use App\Traits\HasComments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model implements Commentable
{
    use HasAccount, HasComments, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'project_id',
        'quote_item_id',
        'category_id',
        'assigned_user_id',
        'title',
        'description',
        'status',
        'priority',
        'billing_status',
        'position',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'billing_status' => TaskBillingStatus::class,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function scopeBillable(Builder $query): void
    {
        $query->where('status', TaskStatus::COMPLETED)
            ->where('billing_status', TaskBillingStatus::Billable);
    }

    /**
     * Members only see tasks assigned to them, mirroring the tasks list.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Account $account): void
    {
        if ($user->isMember($account)) {
            $query->where('assigned_user_id', $user->getKey());
        }
    }

    /**
     * In-memory form of scopeVisibleTo for a loaded task: members only see tasks assigned to them.
     */
    public function isVisibleTo(User $user, Account $account): bool
    {
        return ! $user->isMember($account)
            || ($this->assigned_user_id !== null && (int) $this->assigned_user_id === (int) $user->getKey());
    }

    public function isCommentVisibleTo(User $user): bool
    {
        $this->loadMissing('account');

        return $this->isVisibleTo($user, $this->account);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }
}
