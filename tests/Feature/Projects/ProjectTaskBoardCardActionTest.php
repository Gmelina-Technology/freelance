<?php

use App\Enums\AccountRole;
use App\Filament\App\Resources\Projects\Pages\ProjectTaskBoard;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $scenario = makeQuoteScenario();

    $this->owner = $scenario['owner'];
    $this->account = $scenario['account'];
    $this->project = $scenario['project'];

    $this->previousAssignee = makeAccountUser($this->account, AccountRole::Member);
    $this->assignee = makeAccountUser($this->account, AccountRole::Member);
    $this->project->assignees()->attach([$this->previousAssignee->id, $this->assignee->id]);

    $this->task = Task::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $scenario['client']->id,
        'project_id' => $this->project->id,
        'title' => 'Original title',
        'status' => 'open',
        'assigned_user_id' => $this->previousAssignee->id,
    ]);

    bindFilamentTenant($this->owner, $this->account);
});

function openTaskBoard(Project $project)
{
    return Livewire::test(ProjectTaskBoard::class, ['record' => $project->id]);
}

it('points the card action at the registered edit action', function () {
    $board = openTaskBoard($this->project)->instance();

    $cardAction = $board->getBoard()->resolveCardAction($board->getBoard()->getRecordActions());

    expect($board->getBoard()->getCardAction())->toBe('editTask')
        ->and($cardAction)->toBeInstanceOf(EditAction::class);
});

it('opens the edit form for the task when the card action is mounted', function () {
    openTaskBoard($this->project)
        ->call('mountAction', 'editTask', [], ['recordKey' => $this->task->id])
        ->assertSet('mountedActions.0.name', 'editTask')
        ->assertSet('mountedActions.0.data.id', $this->task->id)
        ->assertSet('mountedActions.0.data.title', 'Original title');
});

it('saves card action edits and notifies the new assignee', function () {
    openTaskBoard($this->project)
        ->call('mountAction', 'editTask', [], ['recordKey' => $this->task->id])
        ->fillForm([
            'title' => 'Updated title',
            'assigned_user_id' => $this->assignee->id,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($this->task->fresh())
        ->title->toBe('Updated title')
        ->assigned_user_id->toBe($this->assignee->id)
        ->and(DatabaseNotification::count())->toBe(1);
});

it('does not open the edit form for a task from another project', function () {
    $otherProject = Project::factory()
        ->for($this->account)
        ->for(Client::factory()->for($this->account)->create(), 'client')
        ->create();
    $otherTask = Task::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $otherProject->client_id,
        'project_id' => $otherProject->id,
        'title' => 'Other project task',
    ]);
    $board = openTaskBoard($this->project);

    expect(fn () => $board->call('mountAction', 'editTask', [], ['recordKey' => $otherTask->id]))
        ->toThrow(TypeError::class);

    expect($otherTask->fresh()->title)->toBe('Other project task');
});

it('keeps the edit and delete record actions registered', function () {
    $board = openTaskBoard($this->project)->instance();
    $actions = collect($board->getBoard()->getRecordActions())->keyBy(fn ($action) => $action->getName());

    expect($actions->keys()->all())->toBe(['editTask', 'delete'])
        ->and($actions['delete'])->toBeInstanceOf(DeleteAction::class);
});
