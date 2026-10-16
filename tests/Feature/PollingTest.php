<?php

use App\Livewire\CommentThread;
use App\Models\Account;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $account = Account::factory()->for($this->owner, 'owner')->create();
    $this->task = Task::factory()->for($account)->create(['client_id' => null, 'project_id' => null]);
});

it('polls the comment thread every 30 seconds while the tab is visible', function () {
    Livewire::actingAs($this->owner)
        ->test(CommentThread::class, ['record' => $this->task])
        ->assertSeeHtml('wire:poll.30s.visible');
});

it('takes the comment poll interval from config', function () {
    config(['comments.poll_interval' => '10s']);

    Livewire::actingAs($this->owner)
        ->test(CommentThread::class, ['record' => $this->task])
        ->assertSeeHtml('wire:poll.10s.visible')
        ->assertDontSeeHtml('wire:poll.30s.visible');
});

it('keeps the app panel notification polling at 30 seconds', function () {
    expect(Filament::getPanel('app')->getDatabaseNotificationsPollingInterval())->toBe('30s');
});
