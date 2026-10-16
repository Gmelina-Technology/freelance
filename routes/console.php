<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired', ['--hours' => 24])->dailyAt('03:00');

Schedule::command('queue:work', [
    '--stop-when-empty',
    '--max-time' => 50,
    '--tries' => 3,
    '--timeout' => 60,
    '--memory' => 128,
])->everyMinute()->withoutOverlapping(5);
