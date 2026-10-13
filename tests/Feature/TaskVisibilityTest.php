<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('agrees between the in-memory check and the query scope', function (AccountRole $role, string $assignment) {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();
    $user = $role === AccountRole::Owner ? $owner : makeAccountUser($account, $role);
    $someoneElse = makeAccountUser($account, AccountRole::Manager);

    $assignee = match ($assignment) {
        'self' => $user->id,
        'other' => $someoneElse->id,
        'nobody' => null,
    };

    $task = Task::factory()->for($account)->create(['assigned_user_id' => $assignee, 'client_id' => null]);

    $visibleByQuery = Task::query()->visibleTo($user, $account)->whereKey($task->id)->exists();

    expect($task->isVisibleTo($user, $account))->toBe($visibleByQuery)
        ->and($task->isCommentVisibleTo($user))->toBe($visibleByQuery);

    $expected = $role !== AccountRole::Member || $assignment === 'self';

    expect($visibleByQuery)->toBe($expected);
})->with([AccountRole::Owner, AccountRole::Manager, AccountRole::Member])
    ->with(['self', 'other', 'nobody']);
