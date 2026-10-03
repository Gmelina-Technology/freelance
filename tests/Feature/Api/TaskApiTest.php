<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/tasks')->assertStatus(401);
});

it('creates a task scoped to the account', function () {
    actingAsToken($this->owner);

    $this->postJson('/api/tasks', [
        'title' => 'Call client about invoice',
        'priority' => 'High',
        'due_date' => '2026-06-30',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Call client about invoice')
        ->assertJsonPath('data.priority', 'High')
        ->assertJsonPath('data.status', 'open');

    expect(Task::where('account_id', $this->account->id)
        ->where('title', 'Call client about invoice')->exists())->toBeTrue();
});

it('rejects an invalid status', function () {
    actingAsToken($this->owner);

    $this->postJson('/api/tasks', ['title' => 'x', 'status' => 'pending'])
        ->assertStatus(422);
});

it('lists only tasks for the authenticated account', function () {
    actingAsToken($this->owner);

    Task::factory()->for($this->account)->create(['title' => 'My task']);
    $other = Account::factory()->for(User::factory(), 'owner')->create();
    Task::factory()->for($other)->create(['title' => 'Their task']);

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJsonFragment(['title' => 'My task'])
        ->assertJsonMissing(['title' => 'Their task']);
});

it('filters overdue tasks', function () {
    actingAsToken($this->owner);

    Task::factory()->for($this->account)->create([
        'title' => 'Overdue', 'status' => 'open', 'due_date' => now()->subDays(2),
    ]);
    Task::factory()->for($this->account)->create([
        'title' => 'Future', 'status' => 'open', 'due_date' => now()->addDays(5),
    ]);

    $this->getJson('/api/tasks?overdue=1')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Overdue'])
        ->assertJsonMissing(['title' => 'Future']);
});

it('updates a task in the account', function () {
    actingAsToken($this->owner);

    $task = Task::factory()->for($this->account)->create(['status' => 'open']);

    $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('cannot update a task from another account', function () {
    actingAsToken($this->owner);

    $other = Account::factory()->for(User::factory(), 'owner')->create();
    $task = Task::factory()->for($other)->create();

    $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])
        ->assertNotFound();
});

it('creates a task with a valid assigned_user_id in the account', function () {
    actingAsToken($this->owner);

    $assignee = User::factory()->create();
    $this->account->users()->syncWithoutDetaching([
        $assignee->id => ['role' => 'member'],
    ]);

    $this->postJson('/api/tasks', [
        'title' => 'Assigned task',
        'assigned_user_id' => $assignee->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.assigned_user_id', $assignee->id);

    expect(Task::where('account_id', $this->account->id)
        ->where('assigned_user_id', $assignee->id)->exists())->toBeTrue();
});

it('updates assigned_user_id on an existing task', function () {
    actingAsToken($this->owner);

    $task = Task::factory()->for($this->account)->create(['assigned_user_id' => null]);

    $assignee = User::factory()->create();
    $this->account->users()->syncWithoutDetaching([
        $assignee->id => ['role' => 'member'],
    ]);

    $this->patchJson("/api/tasks/{$task->id}", ['assigned_user_id' => $assignee->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_user_id', $assignee->id);

    expect($task->fresh()->assigned_user_id)->toBe($assignee->id);
});

it('rejects assigned_user_id from another account with 422', function () {
    actingAsToken($this->owner);

    $outsider = User::factory()->create();
    $otherAccount = Account::factory()->for(User::factory(), 'owner')->create();
    $otherAccount->users()->syncWithoutDetaching([
        $outsider->id => ['role' => 'member'],
    ]);

    $this->postJson('/api/tasks', [
        'title' => 'Bad assign',
        'assigned_user_id' => $outsider->id,
    ])
        ->assertStatus(422);
});

it('keeps a token bound to one account away from the users other accounts', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    Task::factory()->for($this->account)->create(['title' => 'First account task']);
    Task::factory()->for($second)->create(['title' => 'Second account task']);
    actingAsToken($this->owner, ['*'], $second);

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Second account task'])
        ->assertJsonMissing(['title' => 'First account task']);

    $this->postJson('/api/tasks', ['title' => 'New one'])->assertCreated();

    expect(Task::where('title', 'New one')->value('account_id'))->toBe($second->id);
});

it('rejects an account_id that differs from the bound account', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    actingAsToken($this->owner, ['*'], $this->account);

    $this->getJson('/api/tasks?account_id='.$second->id)
        ->assertForbidden()
        ->assertJsonPath('message', 'This token is bound to a different account.');

    $this->postJson('/api/tasks', ['title' => 'Sneaky', 'account_id' => $second->id])->assertForbidden();

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse();
});

it('accepts an account_id equal to the bound account', function () {
    actingAsToken($this->owner, ['*'], $this->account);

    $this->getJson('/api/tasks?account_id='.$this->account->id)->assertOk();
});

it('denies legacy tokens without a bound account', function () {
    actingAsToken($this->owner, ['*'], bound: false);

    $this->getJson('/api/tasks')
        ->assertForbidden()
        ->assertJsonPath('message', 'This token is not bound to an account; create a new token on the API Tokens page.');
});

it('denies a token once the user is removed from its account', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    $this->getJson('/api/tasks')->assertOk();

    $this->account->users()->detach($member);

    $this->getJson('/api/tasks')->assertForbidden();
});
