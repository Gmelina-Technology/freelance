<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | The scheduler runs backup:database daily when enabled. Dumps are gzipped
    | into the path below and deleted after the retention period.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', false),

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

];
