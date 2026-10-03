<?php

use App\Enums\TaskStatus;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Tasks\CompleteTask;
use App\Mcp\Tools\Tasks\CreateTask;
use App\Mcp\Tools\Tasks\DeleteTask;
use App\Mcp\Tools\Tasks\GetTask;
use App\Mcp\Tools\Tasks\ListTasks;
use App\Mcp\Tools\Tasks\UpdateTask;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();

    $this->otherAccount = Account::factory()->for(User::factory(), 'owner')->create();
    $this->foreignTask = Task::factory()->for($this->otherAccount)->create(['title' => 'Foreign task', 'status' => TaskStatus::OPEN]);

    actingAsToken($this->owner, ['*']);
});

it('lists only the account tasks and applies filters', function () {
    Task::factory()->for($this->account)->create(['title' => 'Open one', 'status' => TaskStatus::OPEN]);
    Task::factory()->for($this->account)->create(['title' => 'Done one', 'status' => TaskStatus::COMPLETED]);
    Task::factory()->for($this->account)->create([
        'title' => 'Late one', 'status' => TaskStatus::OPEN, 'due_date' => now()->subDays(3),
    ]);

    BillingServer::tool(ListTasks::class)
        ->assertSee(['Open one', 'Done one', 'Late one'])
        ->assertDontSee('Foreign task');

    BillingServer::tool(ListTasks::class, ['status' => 'completed'])
        ->assertSee('Done one')
        ->assertDontSee('Open one');

    BillingServer::tool(ListTasks::class, ['overdue' => true])
        ->assertSee('Late one')
        ->assertDontSee('Open one');
});

it('filters tasks by project and client', function () {
    $client = Client::factory()->for($this->account)->create();
    $project = Project::factory()->for($this->account)->create(['client_id' => $client->id]);
    Task::factory()->for($this->account)->create(['title' => 'In project', 'project_id' => $project->id, 'client_id' => $client->id]);
    Task::factory()->for($this->account)->create(['title' => 'Loose task']);

    BillingServer::tool(ListTasks::class, ['project_id' => $project->id])
        ->assertSee('In project')
        ->assertDontSee('Loose task');

    BillingServer::tool(ListTasks::class, ['client_id' => $client->id])
        ->assertSee('In project')
        ->assertDontSee('Loose task');
});

it('rejects an invalid status filter', function () {
    BillingServer::tool(ListTasks::class, ['status' => 'pending'])->assertHasErrors(['Validation failed']);
});

it('gets a task', function () {
    $task = Task::factory()->for($this->account)->create(['title' => 'Write report']);

    BillingServer::tool(GetTask::class, ['task_id' => $task->id])->assertSee('Write report');
});

it('cannot read another accounts task', function () {
    BillingServer::tool(GetTask::class, ['task_id' => $this->foreignTask->id])
        ->assertHasErrors(['not found']);
});

it('creates a task with defaults', function () {
    BillingServer::tool(CreateTask::class, ['title' => 'Call client'])
        ->assertOk()
        ->assertSee(['Call client', '"status":"open"', '"priority":"Medium"']);

    expect(Task::where('account_id', $this->account->id)->where('title', 'Call client')->exists())->toBeTrue();
});

it('creates a task linked to the accounts client, project and member', function () {
    $member = User::factory()->create();
    $this->account->users()->attach($member, ['role' => 'member']);
    $client = Client::factory()->for($this->account)->create();
    $project = Project::factory()->for($this->account)->create();

    BillingServer::tool(CreateTask::class, [
        'title' => 'Linked',
        'priority' => 'High',
        'due_date' => '2026-12-31',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'assigned_user_id' => $member->id,
    ])->assertOk()->assertSee(['"priority":"High"', '"due_date":"2026-12-31"']);

    expect(Task::where('title', 'Linked')->first())
        ->account_id->toBe($this->account->id)
        ->client_id->toBe($client->id)
        ->project_id->toBe($project->id);
});

it('validates task input', function () {
    BillingServer::tool(CreateTask::class, ['title' => 'x', 'status' => 'pending'])
        ->assertHasErrors(['Validation failed']);

    BillingServer::tool(CreateTask::class, ['title' => 'x', 'priority' => 'Urgent'])
        ->assertHasErrors(['Validation failed']);

    BillingServer::tool(CreateTask::class, [])->assertHasErrors(['Validation failed']);

    expect(Task::where('account_id', $this->account->id)->count())->toBe(0);
});

it('rejects another accounts client, project or assignee on create', function () {
    $foreignClient = Client::factory()->for($this->otherAccount)->create();
    $foreignProject = Project::factory()->for($this->otherAccount)->create();
    $outsider = User::factory()->create();

    foreach ([
        ['client_id' => $foreignClient->id],
        ['project_id' => $foreignProject->id],
        ['assigned_user_id' => $outsider->id],
    ] as $link) {
        BillingServer::tool(CreateTask::class, ['title' => 'Sneaky'] + $link)
            ->assertHasErrors(['Validation failed']);
    }

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse();
});

it('updates only the given fields and can clear links', function () {
    $client = Client::factory()->for($this->account)->create();
    $task = Task::factory()->for($this->account)->create([
        'title' => 'Before', 'priority' => 'Low', 'client_id' => $client->id,
    ]);

    BillingServer::tool(UpdateTask::class, ['task_id' => $task->id, 'title' => 'After', 'client_id' => null])
        ->assertOk()
        ->assertSee('After');

    $task->refresh();
    expect($task->title)->toBe('After')
        ->and($task->priority->value)->toBe('Low')
        ->and($task->client_id)->toBeNull();
});

it('rejects invalid updates and foreign links', function () {
    $task = Task::factory()->for($this->account)->create();
    $foreignProject = Project::factory()->for($this->otherAccount)->create();

    BillingServer::tool(UpdateTask::class, ['task_id' => $task->id, 'status' => 'bogus'])
        ->assertHasErrors(['Validation failed']);

    BillingServer::tool(UpdateTask::class, ['task_id' => $task->id, 'project_id' => $foreignProject->id])
        ->assertHasErrors(['Validation failed']);

    expect($task->fresh()->project_id)->toBeNull();
});

it('cannot update another accounts task', function () {
    BillingServer::tool(UpdateTask::class, ['task_id' => $this->foreignTask->id, 'title' => 'Hijacked'])
        ->assertHasErrors(['not found']);

    expect($this->foreignTask->fresh()->title)->toBe('Foreign task');
});

it('completes a task', function () {
    $task = Task::factory()->for($this->account)->create(['status' => TaskStatus::OPEN]);

    BillingServer::tool(CompleteTask::class, ['task_id' => $task->id])
        ->assertOk()
        ->assertSee('"status":"completed"');

    expect($task->fresh()->status)->toBe(TaskStatus::COMPLETED);
});

it('cannot complete another accounts task', function () {
    BillingServer::tool(CompleteTask::class, ['task_id' => $this->foreignTask->id])
        ->assertHasErrors(['not found']);

    expect($this->foreignTask->fresh()->status)->not->toBe(TaskStatus::COMPLETED);
});

it('deletes a task', function () {
    $task = Task::factory()->for($this->account)->create();

    BillingServer::tool(DeleteTask::class, ['task_id' => $task->id])->assertOk()->assertSee('"deleted":true');

    expect(Task::find($task->id))->toBeNull();
});

it('cannot delete another accounts task', function () {
    BillingServer::tool(DeleteTask::class, ['task_id' => $this->foreignTask->id])
        ->assertHasErrors(['not found']);

    expect(Task::find($this->foreignTask->id))->not->toBeNull();
});

it('requires the tasks:write ability for writes but not reads', function () {
    $task = Task::factory()->for($this->account)->create();
    actingAsToken($this->owner, ['quotes:write']);

    BillingServer::tool(CreateTask::class, ['title' => 'x'])->assertHasErrors(['tasks:write']);
    BillingServer::tool(UpdateTask::class, ['task_id' => $task->id, 'title' => 'x'])->assertHasErrors(['tasks:write']);
    BillingServer::tool(CompleteTask::class, ['task_id' => $task->id])->assertHasErrors(['tasks:write']);
    BillingServer::tool(DeleteTask::class, ['task_id' => $task->id])->assertHasErrors(['tasks:write']);

    BillingServer::tool(ListTasks::class)->assertHasNoErrors();
    BillingServer::tool(GetTask::class, ['task_id' => $task->id])->assertHasNoErrors();

    expect(Task::find($task->id))->not->toBeNull();
});

it('lets a regular member manage tasks of the account they belong to', function () {
    $member = User::factory()->create();
    $this->account->users()->attach($member, ['role' => 'member']);
    actingAsToken($member, ['tasks:write']);

    BillingServer::tool(CreateTask::class, ['title' => 'From member'])
        ->assertOk()
        ->assertSee('From member');
});

it('keeps a token bound to one account away from the users other accounts', function () {
    $second = Account::factory()->for($this->owner, 'owner')->create();
    $secondTask = Task::factory()->for($second)->create(['title' => 'Second account task']);
    Task::factory()->for($this->account)->create(['title' => 'First account task']);

    actingAsToken($this->owner, ['*'], $this->account);

    BillingServer::tool(ListTasks::class)
        ->assertSee('First account task')
        ->assertDontSee('Second account task');
    BillingServer::tool(GetTask::class, ['task_id' => $secondTask->id])->assertHasErrors(['not found']);
    BillingServer::tool(UpdateTask::class, ['task_id' => $secondTask->id, 'title' => 'Hacked'])->assertHasErrors(['not found']);
    BillingServer::tool(DeleteTask::class, ['task_id' => $secondTask->id])->assertHasErrors(['not found']);

    expect($secondTask->fresh()->title)->toBe('Second account task');
});
