<?php

use App\Enums\AccountRole;
use App\Livewire\CommentThread;
use App\Models\Account;
use App\Models\Comment;
use App\Models\CommentAttachment;
use App\Models\Task;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The policy memoizes membership per user and account id, and ids repeat between refreshed databases.
    (new ReflectionProperty(CommentPolicy::class, 'accountMembershipCache'))->setValue(null, []);
});

function accountWithMember(AccountRole $role = AccountRole::Member): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();

    $account->users()->syncWithoutDetaching([
        $member->id => ['role' => $role->value],
    ]);

    return [$account, $owner, $member];
}

function taskForAccount(Account $account, ?User $assignee = null): Task
{
    return Task::factory()
        ->for($account)
        ->create([
            'client_id' => null,
            'project_id' => null,
            'assigned_user_id' => $assignee?->id,
        ]);
}

it('allows a tenant member to create a task comment through livewire', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->fillForm(['body' => 'This task needs a client update.'])
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = Comment::withNestedReplies()->first();

    expect($comment)
        ->not->toBeNull()
        ->and($comment->account_id)->toBe($account->id)
        ->and($comment->user_id)->toBe($member->id)
        ->and($comment->commentable->is($task))->toBeTrue();
});

it('prevents users outside the account from commenting and downloading attachments', function () {
    [$account, , $member] = accountWithMember();
    $outsideUser = User::factory()->create();
    $task = taskForAccount($account);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'body' => 'Private note.',
        'body_format' => 'rich',
    ]);
    $attachment = CommentAttachment::factory()->for($comment)->create([
        'disk' => 'local',
        'path' => 'comments/private-note.txt',
    ]);

    Storage::fake('local');
    Storage::disk('local')->put($attachment->path, 'private');

    expect(Gate::forUser($outsideUser)->denies('view', $comment))->toBeTrue()
        ->and(Gate::forUser($outsideUser)->denies('create', [Comment::class, $task]))->toBeTrue()
        ->and(Gate::forUser($outsideUser)->denies('reply', $comment))->toBeTrue()
        ->and(Gate::forUser($outsideUser)->denies('react', $comment))->toBeTrue()
        ->and(Gate::forUser($outsideUser)->denies('update', $comment))->toBeTrue()
        ->and(Gate::forUser($outsideUser)->denies('delete', $comment))->toBeTrue();

    $this->actingAs($outsideUser)
        ->get(route('comments.attachments.download', $attachment))
        ->assertForbidden();
});

it('stores only user authors and exposes task comments polymorphically', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account);

    $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'body' => 'Polymorphic task comment.',
        'body_format' => 'rich',
    ]);

    expect(Schema::hasColumn('comments', 'user_id'))->toBeTrue()
        ->and(Schema::hasColumn('comments', 'commenter_type'))->toBeFalse()
        ->and($task->comments()->count())->toBe(1)
        ->and($member->comments()->count())->toBe(1);
});

it('nests replies under parent comments', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);
    $parent = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'body' => 'Parent comment.',
        'body_format' => 'rich',
    ]);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->call('startReply', $parent->id)
        ->set('replyData', ['body' => 'Reply comment.'])
        ->call('addReply')
        ->assertHasNoErrors();

    expect($parent->replies()->count())->toBe(1)
        ->and($task->rootComments()->count())->toBe(1);
});

it('toggles unique reactions per user comment and reaction', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'body' => 'React here.',
        'body_format' => 'rich',
    ]);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->call('toggleReaction', $comment->id, config('comments.allowed_reactions.0'))
        ->call('toggleReaction', $comment->id, config('comments.allowed_reactions.0'));

    expect($comment->reactions()->count())->toBe(0);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->call('toggleReaction', $comment->id, config('comments.allowed_reactions.1'));

    expect($comment->reactions()->count())->toBe(1);
});

it('allows authors owners and managers to moderate comments', function () {
    [$account, $owner, $member] = accountWithMember();
    $manager = User::factory()->create();
    $otherMember = User::factory()->create();
    $account->users()->syncWithoutDetaching([
        $manager->id => ['role' => AccountRole::Manager->value],
        $otherMember->id => ['role' => AccountRole::Member->value],
    ]);
    $task = taskForAccount($account);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'body' => 'Moderate me.',
        'body_format' => 'rich',
    ]);

    expect(Gate::forUser($member)->allows('update', $comment))->toBeTrue()
        ->and(Gate::forUser($member)->allows('delete', $comment))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $comment))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('delete', $comment))->toBeTrue()
        ->and(Gate::forUser($otherMember)->denies('update', $comment))->toBeTrue()
        ->and(Gate::forUser($otherMember)->denies('delete', $comment))->toBeTrue();
});

it('stores attachments privately and authorizes downloads', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);

    Storage::fake('local');

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->set('body', 'See attached file.')
        ->set('attachments', [
            UploadedFile::fake()->create('brief.txt', 1, 'text/plain'),
        ])
        ->call('addComment')
        ->assertHasNoErrors();

    $attachment = CommentAttachment::first();

    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($member)
        ->get(route('comments.attachments.download', $attachment))
        ->assertOk();
});

it('renders the task comments component and submits comments', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->assertSee('No comments yet.')
        ->fillForm(['body' => 'Rendered from the task comments tab.'])
        ->call('addComment')
        ->assertSee('Rendered from the task comments tab.');
});

it('lets owners and managers comment on any task', function (AccountRole $role) {
    [$account, $owner] = accountWithMember();
    $user = $role === AccountRole::Owner ? $owner : makeAccountUser($account, $role);
    $task = taskForAccount($account);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'body' => 'Existing note.',
        'body_format' => 'rich',
    ]);

    expect(Gate::forUser($user)->allows('view', $comment))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [Comment::class, $task]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('react', $comment))->toBeTrue();

    Livewire::actingAs($user)
        ->test(CommentThread::class, ['record' => $task])
        ->assertSee('Existing note.')
        ->fillForm(['body' => 'Seen by '.$role->value])
        ->call('addComment')
        ->assertHasNoErrors();

    expect($task->comments()->count())->toBe(2);
})->with([AccountRole::Owner, AccountRole::Manager]);

it('keeps a member out of the comments of tasks that are not assigned to them', function (bool $assignedToSomeoneElse) {
    [$account, $owner, $member] = accountWithMember();
    $task = taskForAccount($account, $assignedToSomeoneElse ? $owner : null);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'body' => 'Private to the assignee.',
        'body_format' => 'rich',
    ]);
    $attachment = CommentAttachment::factory()->for($comment)->create([
        'disk' => 'local',
        'path' => 'comments/hidden.txt',
    ]);

    Storage::fake('local');
    Storage::disk('local')->put($attachment->path, 'hidden');

    expect(Gate::forUser($member)->denies('view', $comment))->toBeTrue()
        ->and(Gate::forUser($member)->denies('create', [Comment::class, $task]))->toBeTrue()
        ->and(Gate::forUser($member)->denies('reply', $comment))->toBeTrue()
        ->and(Gate::forUser($member)->denies('react', $comment))->toBeTrue();

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->assertDontSee('Private to the assignee.')
        ->fillForm(['body' => 'Sneaking in.'])
        ->call('addComment')
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->call('startReply', $comment->id)
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->call('toggleReaction', $comment->id, config('comments.allowed_reactions.0'))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('comments.attachments.download', $attachment))
        ->assertForbidden();

    expect($task->comments()->count())->toBe(1)
        ->and($comment->reactions()->count())->toBe(0);
})->with([
    'assigned to someone else' => true,
    'unassigned' => false,
]);

it('does not let the client retarget the comment thread', function () {
    [$account, , $member] = accountWithMember();
    $mine = taskForAccount($account, $member);
    $other = taskForAccount($account);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $mine])
        ->set('commentableId', $other->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('does not let the client change the commentable type', function () {
    [$account, , $member] = accountWithMember();
    $mine = taskForAccount($account, $member);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $mine])
        ->set('commentableType', User::class);
})->throws(CannotUpdateLockedPropertyException::class);

it('loads the task the same number of times however many comments the thread has', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account, $member);

    $countTaskQueries = function (int $comments) use ($account, $member, $task): int {
        $task->comments()->delete();

        for ($i = 0; $i < $comments; $i++) {
            $parent = $task->comments()->create([
                'account_id' => $account->id,
                'user_id' => $member->id,
                'body' => "Comment {$i}",
                'body_format' => 'rich',
            ]);
            $task->comments()->create([
                'account_id' => $account->id,
                'user_id' => $member->id,
                'parent_id' => $parent->id,
                'body' => "Reply {$i}",
                'body_format' => 'rich',
            ]);
        }

        $component = Livewire::actingAs($member)->test(CommentThread::class, ['record' => $task]);

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'from "tasks"')) {
                $queries++;
            }
        });

        $component->call('$refresh')->assertSee('Comment '.($comments - 1));

        return $queries;
    };

    $few = $countTaskQueries(2);
    $many = $countTaskQueries(12);

    expect($many)->toBe($few);
});
