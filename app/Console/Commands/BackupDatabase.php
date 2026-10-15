<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('backup:database')]
#[Description('Dump the database to a gzipped file and delete dumps past the retention period')]
class BackupDatabase extends Command
{
    /**
     * Execute the console command. Old dumps are only pruned after a successful dump, so a run of
     * failures never deletes the last good backups.
     */
    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $file = $backups->create();
        } catch (Throwable $exception) {
            Log::error('Database backup failed', ['reason' => $exception->getMessage()]);
            $this->error('Database backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $pruned = $backups->prune();

        Log::info('Database backup created', ['file' => basename($file), 'bytes' => filesize($file)]);
        $this->info('Created '.basename($file).($pruned > 0 ? " and deleted {$pruned} old backup(s)." : '.'));

        return self::SUCCESS;
    }
}
