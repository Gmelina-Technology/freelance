<?php

use App\Enums\AccountRole;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\App\Resources\Clients\ClientResource;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Filament\App\Resources\Quotes\QuoteResource;
use App\Filament\App\Resources\Tasks\TaskResource;
use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);

    $this->account = Account::factory()->create();
    $this->owner = makeAccountUser($this->account, AccountRole::Owner);
    $this->client = Client::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Zephyr Labs',
        'email' => 'ap@zephyr.test',
        'phone' => '555-0100',
        'currency_code' => 'USD',
    ]);

    bindFilamentTenant($this->owner, $this->account);
});

afterEach(function () {
    Model::preventLazyLoading(false);
});

it('finds a client by email or phone and lists its contact details', function (string $term) {
    $results = ClientResource::getGlobalSearchResults($term);

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Zephyr Labs')
        ->and($results->first()->url)->toBe(ClientResource::getUrl('view', ['record' => $this->client]))
        ->and($results->first()->details)->toBe([
            'Email' => 'ap@zephyr.test',
            'Phone' => '555-0100',
            'Currency' => 'USD',
        ]);
})->with(['email' => 'ap@zephyr', 'phone' => '555-0100']);

it('leaves out client details that have no value', function () {
    $this->client->update(['phone' => null, 'currency_code' => null]);

    $details = ClientResource::getGlobalSearchResults('Zephyr')->first()->details;

    expect($details)->toBe(['Email' => 'ap@zephyr.test']);
});

it('finds a project by its client name', function () {
    $project = Project::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'name' => 'Website redesign',
        'status' => ProjectStatus::IN_PROGRESS,
        'due_date' => '2026-11-03',
    ]);

    $result = ProjectResource::getGlobalSearchResults('Zephyr')->first();

    expect($result->title)->toBe('Website redesign')
        ->and($result->url)->toBe(ProjectResource::getUrl('view', ['record' => $project]))
        ->and($result->details)->toBe([
            'Client' => 'Zephyr Labs',
            'Status' => ProjectStatus::IN_PROGRESS->getLabel(),
            'Due' => 'Nov 03, 2026',
        ]);
});

it('shows the invoice amount with the client currency code', function () {
    Invoice::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'number' => 'INV-0042',
        'amount' => 1250,
        'status' => InvoiceStatus::Sent,
        'due_date' => '2026-10-30',
    ]);

    $details = InvoiceResource::getGlobalSearchResults('Zephyr')->first()->details;

    expect($details)->toBe([
        'Client' => 'Zephyr Labs',
        'Status' => InvoiceStatus::Sent->getLabel(),
        'Amount' => 'USD 1,250.00',
        'Due' => 'Oct 30, 2026',
    ]);
});

it('shows the bare invoice amount when the client has no currency', function () {
    $this->client->update(['currency_code' => null]);
    Invoice::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'amount' => 1250,
    ]);

    $details = InvoiceResource::getGlobalSearchResults('Zephyr')->first()->details;

    expect($details['Amount'])->toBe('1,250.00');
});

it('shows the quote amount and valid until date', function () {
    Quote::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'number' => 'QTE-0017',
        'amount' => 900,
        'status' => QuoteStatus::Sent,
        'valid_until' => '2026-10-20',
    ]);

    $details = QuoteResource::getGlobalSearchResults('Zephyr')->first()->details;

    expect($details)->toBe([
        'Client' => 'Zephyr Labs',
        'Status' => QuoteStatus::Sent->getLabel(),
        'Amount' => 'USD 900.00',
        'Valid until' => 'Oct 20, 2026',
    ]);
});

it('finds a task by its project name and links to the top-level edit page', function () {
    $assignee = makeAccountUser($this->account, AccountRole::Member);
    $project = Project::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => $this->client->id,
        'name' => 'Zephyr Launch',
    ]);
    $task = Task::factory()->create([
        'account_id' => $this->account->id,
        'project_id' => $project->id,
        'title' => 'Draft homepage copy',
        'status' => TaskStatus::OPEN,
        'priority' => TaskPriority::High,
        'due_date' => '2026-10-14',
        'assigned_user_id' => $assignee->id,
    ]);

    $result = TaskResource::getGlobalSearchResults('Zephyr Launch')->first();

    expect($result->title)->toBe('Draft homepage copy')
        ->and($result->url)->toBe(TaskResource::getUrl('edit', ['record' => $task]))
        ->and($result->details)->toBe([
            'Project' => 'Zephyr Launch',
            'Status' => TaskStatus::OPEN->getLabel(),
            'Priority' => TaskPriority::High->getLabel(),
            'Due' => 'Oct 14, 2026',
            'Assignee' => $assignee->name,
        ]);
});

it('hides the assignee from members searching tasks', function () {
    $member = makeAccountUser($this->account, AccountRole::Member);
    Task::factory()->create([
        'account_id' => $this->account->id,
        'title' => 'Zephyr own task',
        'assigned_user_id' => $member->id,
    ]);

    bindFilamentTenant($member, $this->account);

    $details = TaskResource::getGlobalSearchResults('Zephyr')->first()->details;

    expect($details)->not->toHaveKey('Assignee');
});

it('loads relation-based details without lazy loading', function (string $resource, string $model) {
    foreach (range(1, 2) as $position) {
        $project = Project::factory()->create([
            'account_id' => $this->account->id,
            'client_id' => $this->client->id,
            'status' => ProjectStatus::IN_PROGRESS,
        ]);

        match ($model) {
            Invoice::class => Invoice::factory()->create([
                'account_id' => $this->account->id,
                'client_id' => $this->client->id,
                'status' => InvoiceStatus::Sent,
            ]),
            Quote::class => Quote::factory()->create([
                'account_id' => $this->account->id,
                'client_id' => $this->client->id,
                'status' => QuoteStatus::Sent,
            ]),
            Task::class => Task::factory()->create([
                'account_id' => $this->account->id,
                'project_id' => $project->id,
                'title' => 'Zephyr task',
                'assigned_user_id' => makeAccountUser($this->account, AccountRole::Member)->id,
            ]),
            default => null,
        };
    }

    Model::preventLazyLoading();

    $results = $resource::getGlobalSearchResults('Zephyr');

    expect($results)->toHaveCount(2)
        ->and($results->first()->details)->toHaveKey($model === Task::class ? 'Project' : 'Client');

    if ($model === Task::class) {
        expect($results->first()->details)->toHaveKey('Assignee');
    }
})->with([
    'projects' => [ProjectResource::class, Project::class],
    'invoices' => [InvoiceResource::class, Invoice::class],
    'quotes' => [QuoteResource::class, Quote::class],
    'tasks' => [TaskResource::class, Task::class],
]);
