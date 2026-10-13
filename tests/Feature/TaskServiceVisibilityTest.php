<?php

use App\Enums\AccountRole;
use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->member = makeAccountUser($this->account, AccountRole::Member);
    $this->manager = makeAccountUser($this->account, AccountRole::Manager);
    $this->service = app(TaskService::class);

    $this->mine = Task::factory()->for($this->account)->create(['assigned_user_id' => $this->member->id, 'client_id' => null]);
    $this->theirs = Task::factory()->for($this->account)->create(['assigned_user_id' => $this->manager->id, 'client_id' => null]);
    $this->unassigned = Task::factory()->for($this->account)->create(['assigned_user_id' => null, 'client_id' => null]);
});

it('lists only the assigned tasks for a member', function () {
    $ids = $this->service->list($this->account, $this->member)->pluck('id')->all();

    expect($ids)->toBe([$this->mine->id]);
});

it('lists every task for owners and managers', function (string $who) {
    $ids = $this->service->list($this->account, $this->{$who})->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$this->mine->id, $this->theirs->id, $this->unassigned->id]);
})->with(['owner', 'manager']);

it('lets a member find, update, complete and delete an assigned task', function () {
    expect($this->service->find($this->account, $this->member, $this->mine->id)->is($this->mine))->toBeTrue();

    $this->service->update($this->account, $this->member, $this->mine->id, ['title' => 'Renamed']);
    expect($this->mine->fresh()->title)->toBe('Renamed');

    $this->service->complete($this->account, $this->member, $this->mine->id);
    expect($this->mine->fresh()->status)->toBe(TaskStatus::COMPLETED);

    $this->service->delete($this->account, $this->member, $this->mine->id);
    expect(Task::find($this->mine->id))->toBeNull();
});

it('lets owners and managers act on any task', function (string $who) {
    $user = $this->{$who};

    $this->service->update($this->account, $user, $this->theirs->id, ['title' => 'Changed by '.$who]);
    expect($this->theirs->fresh()->title)->toBe('Changed by '.$who);

    $this->service->delete($this->account, $user, $this->unassigned->id);
    expect(Task::find($this->unassigned->id))->toBeNull();
})->with(['owner', 'manager']);

it('hides other tasks from a member as if they did not exist', function (string $task) {
    $target = $this->{$task};
    $title = $target->title;

    expect(fn () => $this->service->find($this->account, $this->member, $target->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->service->update($this->account, $this->member, $target->id, ['title' => 'Hacked']))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->service->complete($this->account, $this->member, $target->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->service->delete($this->account, $this->member, $target->id))->toThrow(ModelNotFoundException::class);

    expect($target->fresh()->title)->toBe($title)
        ->and($target->fresh()->status)->toBe($target->status);
})->with(['theirs', 'unassigned']);
