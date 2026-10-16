<?php

use App\Models\Account;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @return array<int, array<int, string>>
 */
function taskAccountIndexes(): array
{
    return [['account_id', 'status'], ['account_id', 'due_date'], ['account_id', 'assigned_user_id']];
}

function taskIndexesMigration(): Migration
{
    return require database_path('migrations/2026_10_15_000003_add_account_indexes_to_tasks_table.php');
}

it('adds the account-scoped task indexes', function () {
    foreach (taskAccountIndexes() as $columns) {
        expect(Schema::hasIndex('tasks', $columns))->toBeTrue(implode(',', $columns));
    }
});

it('removes all three indexes on rollback and tasks still work, then adds them back', function () {
    taskIndexesMigration()->down();

    foreach (taskAccountIndexes() as $columns) {
        expect(Schema::hasIndex('tasks', $columns))->toBeFalse(implode(',', $columns));
    }

    $account = Account::factory()->create();
    expect(Task::factory()->for($account)->create()->exists)->toBeTrue();

    taskIndexesMigration()->up();

    foreach (taskAccountIndexes() as $columns) {
        expect(Schema::hasIndex('tasks', $columns))->toBeTrue(implode(',', $columns));
    }
});
