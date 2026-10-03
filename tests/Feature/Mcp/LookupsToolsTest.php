<?php

use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\Lookups\ListCategories;
use App\Mcp\Tools\Lookups\ListClients;
use App\Mcp\Tools\Lookups\ListProjects;
use App\Mcp\Tools\Lookups\ListUnits;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->otherAccount = Account::factory()->for(User::factory(), 'owner')->create();

    actingAsToken($this->owner, ['tasks:write']);
});

it('lists only the accounts clients', function () {
    Client::factory()->for($this->account)->create(['name' => 'Acme Ltd']);
    Client::factory()->for($this->otherAccount)->create(['name' => 'Rival Inc']);

    BillingServer::tool(ListClients::class)
        ->assertSee('Acme Ltd')
        ->assertDontSee('Rival Inc');
});

it('lists projects and filters them by client', function () {
    $acme = Client::factory()->for($this->account)->create();
    $globex = Client::factory()->for($this->account)->create();
    Project::factory()->for($this->account)->create(['client_id' => $acme->id, 'name' => 'Acme Website']);
    Project::factory()->for($this->account)->create(['client_id' => $globex->id, 'name' => 'Globex App']);
    Project::factory()->for($this->otherAccount)->create(['name' => 'Foreign Project']);

    BillingServer::tool(ListProjects::class)
        ->assertSee(['Acme Website', 'Globex App'])
        ->assertDontSee('Foreign Project');

    BillingServer::tool(ListProjects::class, ['client_id' => $acme->id])
        ->assertSee('Acme Website')
        ->assertDontSee('Globex App');
});

it('returns no projects when filtering by another accounts client', function () {
    $foreignClient = Client::factory()->for($this->otherAccount)->create();
    Project::factory()->for($this->otherAccount)->create(['client_id' => $foreignClient->id, 'name' => 'Foreign Project']);

    BillingServer::tool(ListProjects::class, ['client_id' => $foreignClient->id])
        ->assertDontSee('Foreign Project');
});

it('lists only the accounts units', function () {
    Unit::factory()->create(['account_id' => $this->account->id, 'name' => 'hour']);
    Unit::factory()->create(['account_id' => $this->otherAccount->id, 'name' => 'fortnight']);

    BillingServer::tool(ListUnits::class)
        ->assertSee('hour')
        ->assertDontSee('fortnight');
});

it('lists only the accounts categories', function () {
    Category::factory()->create(['account_id' => $this->account->id, 'name' => 'Design']);
    Category::factory()->create(['account_id' => $this->otherAccount->id, 'name' => 'Secret']);

    BillingServer::tool(ListCategories::class)
        ->assertSee('Design')
        ->assertDontSee('Secret');
});
