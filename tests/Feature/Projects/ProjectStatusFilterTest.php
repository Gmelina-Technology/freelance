<?php

use App\Enums\AccountRole;
use App\Enums\ProjectStatus;
use App\Filament\App\Resources\Projects\Pages\ListProjects;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->owner = makeAccountUser($this->account, AccountRole::Owner);
    $this->client = Client::factory()->create(['account_id' => $this->account->id]);

    Model::preventLazyLoading(false);

    bindFilamentTenant($this->owner, $this->account);
});

it('filters the projects table by status', function () {
    $backlog = Project::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'status' => ProjectStatus::BACKLOG,
    ]);
    $blocked = Project::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'status' => ProjectStatus::BLOCKED,
    ]);

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$backlog, $blocked])
        ->filterTable('status', ProjectStatus::BLOCKED->value)
        ->assertCanSeeTableRecords([$blocked])
        ->assertCanNotSeeTableRecords([$backlog]);
});
