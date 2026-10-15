<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Point the backup at a temporary directory and a MySQL connection, with a fake mysqldump.
 */
beforeEach(function () {
    $this->backupPath = sys_get_temp_dir().'/backup-test-'.uniqid();

    config([
        'backup.path' => $this->backupPath,
        'backup.retention_days' => 14,
        'database.default' => 'mysql',
        'database.connections.mysql' => [
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => '3306',
            'database' => 'freelance',
            'username' => 'backup_user',
            'password' => 'hunter2-secret',
        ],
    ]);

    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(2, 30));
});

afterEach(function () {
    File::deleteDirectory($this->backupPath);
});

/**
 * A fake mysqldump that writes its --result-file like the real one does.
 */
function fakeMysqldump(string $sql = "-- fake dump\nCREATE TABLE t (id int);\n", int $exitCode = 0, string $stderr = ''): void
{
    Process::fake(function (PendingProcess $process) use ($sql, $exitCode, $stderr): ProcessResult {
        foreach ($process->command as $argument) {
            if (str_starts_with($argument, '--result-file=') && $exitCode === 0) {
                file_put_contents(substr($argument, strlen('--result-file=')), $sql);
            }
        }

        return Process::result(errorOutput: $stderr, exitCode: $exitCode);
    });
}

it('writes a gzipped dump that contains the dumped SQL', function () {
    fakeMysqldump("-- marker\nSELECT 1;\n");

    $this->artisan('backup:database')->assertSuccessful();

    $files = File::glob($this->backupPath.'/db-*.sql.gz');

    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->toBe('db-20261015-023000.sql.gz')
        ->and(gzdecode(file_get_contents($files[0])))->toBe("-- marker\nSELECT 1;\n")
        ->and(File::glob($this->backupPath.'/*.partial'))->toBeEmpty()
        ->and(File::exists($this->backupPath.'/.htaccess'))->toBeTrue();

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(substr(sprintf('%o', fileperms($files[0])), -4))->toBe('0600');
    }
});

it('passes the password through the environment and keeps it off the command line', function () {
    fakeMysqldump();

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function (PendingProcess $process): bool {
        return in_array('--single-transaction', $process->command, true)
            && in_array('--no-tablespaces', $process->command, true)
            && in_array('--host=db.internal', $process->command, true)
            && in_array('--user=backup_user', $process->command, true)
            && end($process->command) === 'freelance'
            && ($process->environment['MYSQL_PWD'] ?? null) === 'hunter2-secret'
            && ! str_contains(json_encode($process->command), 'hunter2-secret');
    });
});

it('deletes dumps past the retention period and nothing else', function () {
    fakeMysqldump();
    File::ensureDirectoryExists($this->backupPath);
    $old = $this->backupPath.'/db-20260901-020000.sql.gz';
    $justOver = $this->backupPath.'/db-20261001-022959.sql.gz';
    $recent = $this->backupPath.'/db-20261002-023000.sql.gz';
    $unrelated = $this->backupPath.'/db-latest.sql.gz';
    $notes = $this->backupPath.'/notes.txt';

    foreach ([$old, $justOver, $recent, $unrelated, $notes] as $file) {
        file_put_contents($file, 'x');
    }

    $this->artisan('backup:database')->assertSuccessful();

    expect(File::exists($old))->toBeFalse()
        ->and(File::exists($justOver))->toBeFalse()
        ->and(File::exists($recent))->toBeTrue()
        ->and(File::exists($unrelated))->toBeTrue()
        ->and(File::exists($notes))->toBeTrue();
});

it('fails, logs an error and leaves no partial files when mysqldump exits non-zero', function () {
    Log::spy();
    fakeMysqldump(exitCode: 2, stderr: 'Access denied for user');
    File::ensureDirectoryExists($this->backupPath);
    file_put_contents($oldDump = $this->backupPath.'/db-20260901-020000.sql.gz', 'x');

    $this->artisan('backup:database')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Database backup failed'
        && str_contains($context['reason'], 'Access denied')
        && ! str_contains($context['reason'], 'hunter2-secret'))->once();

    expect(File::glob($this->backupPath.'/db-20261015*'))->toBeEmpty()
        ->and(File::exists($oldDump))->toBeTrue();
});

it('fails and logs an error when processes cannot be started', function () {
    Log::spy();
    Process::fake(fn () => throw new LogicException('The Process class relies on proc_open, which is not available on your PHP installation.'));
    File::ensureDirectoryExists($this->backupPath);
    file_put_contents($oldDump = $this->backupPath.'/db-20260901-020000.sql.gz', 'x');

    $this->artisan('backup:database')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Database backup failed'
        && str_contains($context['reason'], 'proc_open'))->once();

    expect(File::glob($this->backupPath.'/*.partial'))->toBeEmpty()
        ->and(File::exists($oldDump))->toBeTrue();
});

it('refuses to back up anything but MySQL and starts no process', function () {
    Log::spy();
    Process::fake();
    config(['database.default' => 'sqlite', 'database.connections.sqlite.driver' => 'sqlite']);

    $this->artisan('backup:database')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => str_contains($context['reason'], 'MySQL only'))->once();
    Process::assertNothingRan();
});
