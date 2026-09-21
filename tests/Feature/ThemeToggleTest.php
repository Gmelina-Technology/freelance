<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('site pages render the theme toggle and the theme bootstrap script', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee("localStorage.getItem('theme')", false);
})->with([
    'home' => ['/'],
    'terms' => ['/terms'],
    'privacy' => ['/privacy'],
]);
