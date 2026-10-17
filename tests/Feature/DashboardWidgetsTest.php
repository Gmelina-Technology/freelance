<?php

use App\Filament\App\Widgets\TasksByTeamChart;
use App\Filament\App\Widgets\TaskStatsWidget;
use App\Models\Account;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Wednesday, so the current week runs Mon 2026-06-15 to Sun 2026-06-21.
    $this->travelTo(CarbonImmutable::parse('2026-06-17 10:00:00'));

    $this->owner = User::factory()->create(['name' => 'Olive Owner']);
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
    $this->helper = User::factory()->create(['name' => 'Hank Helper']);
    $this->account->users()->attach([$this->owner->id => ['role' => 'owner'], $this->helper->id => ['role' => 'member']]);

    $this->makeTask = fn (string $status, ?string $due, ?User $assignee) => Task::factory()->create([
        'account_id' => $this->account->id,
        'client_id' => Client::factory()->create(['account_id' => $this->account->id])->id,
        'status' => $status,
        'due_date' => $due,
        'assigned_user_id' => $assignee?->id,
    ]);

    bindFilamentTenant($this->owner, $this->account);
});

/**
 * @return array<string, mixed>
 */
function widgetData(object $component): array
{
    return (fn (): array => $this->getData())->call($component->instance());
}

it('counts overdue, this-week and pending tasks for the account only', function () {
    ($this->makeTask)('open', '2026-06-10 09:00:00', $this->owner); // overdue
    ($this->makeTask)('in_progress', '2026-06-16 09:00:00', $this->owner); // overdue and this week
    ($this->makeTask)('open', '2026-06-19 09:00:00', $this->helper); // this week
    ($this->makeTask)('open', '2026-07-30 09:00:00', null); // later
    ($this->makeTask)('open', null, $this->owner); // no due date
    ($this->makeTask)('completed', '2026-06-10 09:00:00', $this->owner); // ignored
    ($this->makeTask)('review', '2026-06-18 09:00:00', $this->owner); // ignored

    $other = Account::factory()->create();
    Task::withoutEvents(fn () => Task::factory()->create(['account_id' => $other->id, 'status' => 'open', 'due_date' => '2026-06-10 09:00:00'])); // other account

    $stats = collect((fn (): array => $this->getStats())->call(Livewire::test(TaskStatsWidget::class)->instance()))
        ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()]);

    expect($stats['Overdue Tasks'])->toBe(2)
        ->and($stats['Tasks This Week'])->toBe(2)
        ->and($stats['Pending Tasks'])->toBe(5);
});

it('returns zero counts for an account without tasks', function () {
    $stats = collect((fn (): array => $this->getStats())->call(Livewire::test(TaskStatsWidget::class)->instance()))
        ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()]);

    expect($stats['Overdue Tasks'])->toBe(0)
        ->and($stats['Tasks This Week'])->toBe(0)
        ->and($stats['Pending Tasks'])->toBe(0);
});

it('charts open and in-progress task counts per assignee', function () {
    ($this->makeTask)('open', null, $this->owner);
    ($this->makeTask)('in_progress', null, $this->owner);
    ($this->makeTask)('open', null, $this->helper);
    ($this->makeTask)('open', null, null);
    ($this->makeTask)('completed', null, $this->helper);
    $other = Account::factory()->create();
    Task::withoutEvents(fn () => Task::factory()->create(['account_id' => $other->id, 'status' => 'open', 'assigned_user_id' => $this->helper->id])); // other account

    $data = widgetData(Livewire::test(TasksByTeamChart::class));

    $series = array_combine($data['labels'], $data['datasets'][0]['data']);

    ksort($series);

    expect($series)->toBe(['Hank Helper' => 1, 'Olive Owner' => 2, 'Unassigned' => 1]);
});

it('loads the team chart without hydrating tasks', function () {
    foreach (range(1, 5) as $_) {
        ($this->makeTask)('open', null, $this->owner);
    }

    $component = Livewire::test(TasksByTeamChart::class);

    DB::enableQueryLog();
    widgetData($component);

    expect(DB::getQueryLog())->toHaveCount(2);
});

it('loads the task stats with a single query', function () {
    ($this->makeTask)('open', '2026-06-10 09:00:00', $this->owner);

    $component = Livewire::test(TaskStatsWidget::class);

    DB::enableQueryLog();
    (fn (): array => $this->getStats())->call($component->instance());

    expect(DB::getQueryLog())->toHaveCount(1);
});
