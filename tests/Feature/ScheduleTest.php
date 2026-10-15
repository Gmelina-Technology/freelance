<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The scheduled events in definition order, keyed by a readable command name.
 *
 * @return array<int, Event>
 */
function scheduledEvents(): array
{
    return app(Schedule::class)->events();
}

function scheduledEvent(string $command): Event
{
    return collect(scheduledEvents())->first(fn (Event $event): bool => str_contains($event->command, $command))
        ?? test()->fail("{$command} is not scheduled.");
}

function scheduledPosition(string $command): int
{
    return collect(scheduledEvents())->search(fn (Event $event): bool => str_contains($event->command, $command));
}

it('runs a short-lived queue worker every minute without overlapping runs', function () {
    $worker = scheduledEvent('queue:work');

    expect($worker->command)
        ->toContain('--stop-when-empty')
        ->toContain('--max-time=50')
        ->toContain('--tries=3')
        ->toContain('--timeout=60')
        ->and($worker->expression)->toBe('* * * * *')
        ->and($worker->withoutOverlapping)->toBeTrue()
        ->and($worker->expiresAt)->toBe(5);
});

it('keeps the worker timeout below the queue retry_after', function () {
    expect(60)->toBeLessThan((int) config('queue.connections.database.retry_after'));
});

it('prunes expired API tokens daily', function () {
    $prune = scheduledEvent('sanctum:prune-expired');

    expect($prune->command)->toContain('--hours=24')
        ->and($prune->expression)->toBe('0 3 * * *');
});

it('backs up the database daily at 02:30, before the worker', function () {
    $backup = scheduledEvent('backup:database');

    expect($backup->expression)->toBe('30 2 * * *')
        ->and($backup->withoutOverlapping)->toBeTrue()
        ->and(scheduledPosition('backup:database'))->toBeLessThan(scheduledPosition('queue:work'));
});

it('runs the backup only when backups are enabled', function (bool $enabled) {
    config(['backup.enabled' => $enabled]);

    expect(scheduledEvent('backup:database')->filtersPass(app()))->toBe($enabled);
})->with([true, false]);
