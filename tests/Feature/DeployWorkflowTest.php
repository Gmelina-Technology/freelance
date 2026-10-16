<?php

function workflow(string $name): string
{
    return file_get_contents(base_path(".github/workflows/{$name}.yml"));
}

/**
 * The shell script the deploy runs on the server, dedented.
 */
function remoteDeployScript(): string
{
    preg_match('/^ {10}script: \|\n(.*?)(?=^ {6}- name: Notify on success)/ms', workflow('deploy'), $matches);

    return preg_replace('/^ {12}/m', '', $matches[1]);
}

it('pins the SSH action to a full commit SHA', function () {
    expect(workflow('deploy'))
        ->toMatch('/appleboy\/ssh-action@[0-9a-f]{40}\b/')
        ->not->toContain('ssh-action@master');
});

it('runs one deploy at a time and never cancels a running one', function () {
    expect(workflow('deploy'))
        ->toContain('group: deploy-production')
        ->toContain('cancel-in-progress: false');
});

it('names every missing secret before connecting', function () {
    expect(workflow('deploy'))
        ->toContain('Missing deploy secrets')
        ->toContain('DEPLOY_SERVER DEPLOY_USER DEPLOY_KEY DEPLOY_PATH');
});

it('passes secrets to the server through envs and never writes them into the script', function () {
    $script = remoteDeployScript();

    expect(workflow('deploy'))->toContain('envs: DEPLOY_PATH,DEPLOY_SHA,DEPLOY_MAINTENANCE_SECRET,DEPLOY_PHP_BIN')
        ->and($script)->not->toContain('${{')
        ->and($script)->toContain('$DEPLOY_PATH');
});

it('builds nothing on the runner', function () {
    expect(workflow('deploy'))
        ->not->toContain('npm ')
        ->not->toContain('setup-node')
        ->not->toContain('composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader'."\n      - name");
});

it('takes the SSH port from the DEPLOY_PORT variable, defaulting to 22', function () {
    expect(workflow('deploy'))->toContain("port: \${{ vars.DEPLOY_PORT || '22' }}");
});

it('runs the server steps in the safe order', function () {
    $script = remoteDeployScript();

    $steps = [
        'set -Eeuo pipefail',
        'version_compare(PHP_VERSION, "8.4.0"',
        'about --only=environment --json',
        'artisan down',
        'git merge --ff-only "$DEPLOY_SHA"',
        'composer install --no-dev',
        'artisan migrate --force',
        'artisan optimize',
        'artisan queue:restart',
        'artisan up',
    ];

    // The trap function also contains an 'artisan up', so the final one is the last occurrence.
    $positions = array_map(
        fn (string $step): int|false => $step === 'artisan up' ? strrpos($script, $step) : strpos($script, $step),
        $steps,
    );

    expect($positions)->each->not->toBeFalse()
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());

    expect(strpos($script, 'trap bring_up EXIT'))->toBeLessThan(strpos($script, 'artisan down --retry=60'))
        ->and($script)->toContain('Previous release:')
        ->and($script)->toContain('--secret="$DEPLOY_MAINTENANCE_SECRET"');
});

it('only brings the site up from the trap when it was taken down', function () {
    expect(remoteDeployScript())
        ->toContain('bring_up() { if [ "$DOWN_RAN" = 1 ]; then')
        ->toContain('DOWN_RAN=1')
        ->toContain('DOWN_RAN=0');
});

it('keeps the debug check cache path inside the app directory', function () {
    expect(workflow('deploy'))
        ->not->toContain('/tmp/deploy-nocache')
        ->not->toContain('/tmp/')
        ->toContain('APP_CONFIG_CACHE="$DEPLOY_PATH/bootstrap/cache/deploy-nocache-$$.php"');
});

it('is valid bash', function () {
    $file = tempnam(sys_get_temp_dir(), 'deploy');
    file_put_contents($file, remoteDeployScript());

    exec('bash -n '.escapeshellarg($file).' 2>&1', $output, $status);
    unlink($file);

    expect($status)->toBe(0, implode("\n", $output));
})->skip(PHP_OS_FAMILY === 'Windows', 'bash -n runs in CI');

it('tests on PHP 8.4 and checks the committed assets right after building them', function () {
    $tests = workflow('test');

    expect($tests)
        ->toContain('php-version: 8.4')
        ->toContain('git status --porcelain -- public/build')
        ->and(strpos($tests, 'name: Build assets'))->toBeLessThan(strpos($tests, 'Check committed assets match the build'))
        ->and(strpos($tests, 'Check committed assets match the build'))->toBeLessThan(strpos($tests, 'Run Pint code style checks'));
});
