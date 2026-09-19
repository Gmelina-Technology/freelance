<?php

use App\Enums\TaskStatus;
use App\Filament\App\Resources\Tasks\Pages\ListTasks;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();

    $this->openTask = Task::factory()->create([
        'account_id' => $this->account->id,
        'status' => TaskStatus::OPEN,
    ]);
    $this->completedTask = Task::factory()->completed()->create([
        'account_id' => $this->account->id,
    ]);

    bindFilamentTenant($this->owner, $this->account);
});

it('hides completed tasks by default', function () {
    Livewire::test(ListTasks::class)
        ->assertCanSeeTableRecords([$this->openTask])
        ->assertCanNotSeeTableRecords([$this->completedTask]);
});

it('shows only completed tasks when the completed filter is set to yes', function () {
    Livewire::test(ListTasks::class)
        ->filterTable('completed', true)
        ->assertCanSeeTableRecords([$this->completedTask])
        ->assertCanNotSeeTableRecords([$this->openTask]);
});

it('shows every task when the completed filter is cleared', function () {
    Livewire::test(ListTasks::class)
        ->filterTable('completed', null)
        ->assertCanSeeTableRecords([$this->openTask, $this->completedTask]);
});
