<?php

use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/tasks')->assertStatus(401);
});

it('creates a task scoped to the account', function () {
    Sanctum::actingAs($this->owner);

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
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/tasks', ['title' => 'x', 'status' => 'pending'])
        ->assertStatus(422);
});

it('lists only tasks for the authenticated account', function () {
    Sanctum::actingAs($this->owner);

    Task::factory()->for($this->account)->create(['title' => 'My task']);
    $other = Account::factory()->for(User::factory(), 'owner')->create();
    Task::factory()->for($other)->create(['title' => 'Their task']);

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJsonFragment(['title' => 'My task'])
        ->assertJsonMissing(['title' => 'Their task']);
});

it('filters overdue tasks', function () {
    Sanctum::actingAs($this->owner);

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
    Sanctum::actingAs($this->owner);

    $task = Task::factory()->for($this->account)->create(['status' => 'open']);

    $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('cannot update a task from another account', function () {
    Sanctum::actingAs($this->owner);

    $other = Account::factory()->for(User::factory(), 'owner')->create();
    $task = Task::factory()->for($other)->create();

    $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])
        ->assertNotFound();
});
