<?php

use App\Enums\AccountRole;
use App\Livewire\CommentThread;
use App\Models\Account;
use App\Models\Comment;
use App\Models\CommentAttachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

function taskForAccount(Account $account): Task
{
    return Task::factory()
        ->for($account)
        ->create([
            'client_id' => null,
            'project_id' => null,
            'assigned_user_id' => null,
        ]);
}

it('allows a tenant member to create a task comment through livewire', function () {
    [$account, , $member] = accountWithMember();
    $task = taskForAccount($account);

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
    $task = taskForAccount($account);
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
    $task = taskForAccount($account);
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
    $task = taskForAccount($account);

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
    $task = taskForAccount($account);

    Livewire::actingAs($member)
        ->test(CommentThread::class, ['record' => $task])
        ->assertSee('No comments yet.')
        ->fillForm(['body' => 'Rendered from the task comments tab.'])
        ->call('addComment')
        ->assertSee('Rendered from the task comments tab.');
});
