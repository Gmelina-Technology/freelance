<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * KEY=value lines of an env template, ignoring comments.
 *
 * @return array<string, string>
 */
function envTemplate(string $file): array
{
    $values = [];

    foreach (file(base_path($file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[$key] = $value;
    }

    return $values;
}

it('keeps business events in their own daily log at info', function () {
    expect(config('logging.channels.business'))->toMatchArray([
        'driver' => 'daily',
        'level' => 'info',
        'days' => 30,
    ]);
});

it('keeps the application log for 14 days', function () {
    expect(config('logging.channels.daily.days'))->toBe(14);
});

it('ships a production env template with safe defaults and no secrets', function () {
    $env = envTemplate('.env.production.example');

    expect($env)->toMatchArray([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LOG_STACK' => 'daily',
        'LOG_LEVEL' => 'warning',
        'SESSION_SECURE_COOKIE' => 'true',
        'ADMIN_EMAILS' => '',
        'QUEUE_CONNECTION' => 'database',
        'DB_CONNECTION' => 'mysql',
    ])
        ->and($env['APP_KEY'])->toBe('')
        ->and($env['DB_PASSWORD'])->toBe('')
        ->and($env)->not->toHaveKey('BACKUP_ENABLED')
        ->and($env['MAIL_PASSWORD'])->toBe('');
});

it('lists the new settings in the development env template', function () {
    $env = envTemplate('.env.example');

    expect($env)->toMatchArray([
        'ADMIN_EMAILS' => '',
        'API_RATE_LIMIT_PER_MINUTE' => '60',
    ])
        ->and($env)->not->toHaveKey('BACKUP_ENABLED');
});

it('serves the home page from a view route that can be cached', function () {
    $this->get('/')->assertOk();

    $route = Route::getRoutes()->match(Request::create('/'));

    expect($route)->toBeInstanceOf(LaravelRoute::class)
        ->and($route->getAction('uses'))->not->toBeInstanceOf(Closure::class)
        ->and($route->defaults)->toHaveKey('view', 'welcome');
});

it('no longer has the unused revenue widget', function () {
    expect(app_path('Filament/App/Widgets/RevenueStatsWidget.php'))->not->toBeFile();
});

it('documents production operations in the README', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)
        ->toContain('## Production operations')
        ->toContain('artisan schedule:run')
        ->toContain('DEPLOY_PORT')
        ->toContain('restore the most recent hPanel backup')
        ->not->toContain('backup:database')
        ->not->toContain('mysqldump')
        ->not->toContain('pre-deploy dump')
        ->toContain('ADMIN_EMAILS');
});
