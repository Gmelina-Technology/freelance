<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DatabaseBackupService
{
    /**
     * Dump the default MySQL connection into a gzipped file under the backup path.
     *
     * @return string absolute path of the new .sql.gz
     *
     * @throws RuntimeException on any failure; the message never contains the database password
     */
    public function create(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backups support MySQL only.');
        }

        $directory = $this->prepareDirectory();
        $name = 'db-'.now()->format('Ymd-His');
        $sql = "{$directory}/{$name}.sql.partial";
        $gzip = "{$directory}/{$name}.sql.gz";

        try {
            $result = Process::env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->timeout(600)
                ->run([
                    config('backup.mysqldump'),
                    '--single-transaction',
                    '--quick',
                    '--no-tablespaces',
                    '--skip-lock-tables',
                    '--host='.$connection['host'],
                    '--port='.$connection['port'],
                    '--user='.$connection['username'],
                    '--result-file='.$sql,
                    $connection['database'],
                ]);

            if ($result->failed()) {
                throw new RuntimeException('mysqldump failed with exit code '.$result->exitCode().': '.trim($result->errorOutput()));
            }

            $this->compress($sql, "{$gzip}.partial");

            chmod("{$gzip}.partial", 0600);
            rename("{$gzip}.partial", $gzip);
        } finally {
            File::delete([$sql, "{$gzip}.partial"]);
        }

        return $gzip;
    }

    /**
     * Delete dumps older than the retention period, going by the timestamp in the file name.
     * Files that do not match the dump naming scheme are left alone.
     *
     * @return int number of files deleted
     */
    public function prune(?CarbonInterface $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subDays((int) config('backup.retention_days'));
        $deleted = 0;

        foreach (File::glob(config('backup.path').'/db-*.sql.gz') as $file) {
            if (! preg_match('/^db-(\d{8}-\d{6})\.sql\.gz$/', basename($file), $matches)) {
                continue;
            }

            if (Carbon::createFromFormat('Ymd-His', $matches[1])->lt($cutoff) && File::delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Create the backup directory, closed to other users and to the web server.
     */
    private function prepareDirectory(): string
    {
        $directory = rtrim((string) config('backup.path'), '/\\');

        File::ensureDirectoryExists($directory, 0700);
        File::put("{$directory}/.htaccess", "Require all denied\n");

        return $directory;
    }

    /**
     * Stream-compress a file so the dump never has to fit in PHP memory.
     */
    private function compress(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb9');

        if ($input === false || $output === false) {
            throw new RuntimeException('Could not compress the database dump.');
        }

        try {
            while (! feof($input)) {
                gzwrite($output, (string) fread($input, 1024 * 1024));
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }
}
