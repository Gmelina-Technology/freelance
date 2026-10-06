<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('site pages render the cookie banner, both choices, and the privacy link', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('data-cookie-banner', false)
        ->assertSee('data-cookie-accept', false)
        ->assertSee('data-cookie-reject', false)
        ->assertSee(route('privacy'), false)
        ->assertSee("const STORAGE_KEY = 'cookie-consent'", false);
})->with([
    'home' => ['/'],
    'terms' => ['/terms'],
    'privacy' => ['/privacy'],
]);

test('site pages render the cookie preferences footer link', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('data-cookie-preferences', false)
        ->assertSee('Cookie preferences');
})->with([
    'home' => ['/'],
    'terms' => ['/terms'],
    'privacy' => ['/privacy'],
]);

test('the consent script exposes the consent hook', function () {
    $this->get('/')
        ->assertSee('window.cookieConsent', false)
        ->assertSee('cookie-consent:change', false);
});

test('privacy policy no longer claims there is no cookie banner', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertDontSee('no cookie banner')
        ->assertSee('cookie-consent')
        ->assertSee('Cookie preferences');
});

test('panel auth pages do not show the cookie banner', function () {
    $this->get(route('filament.app.auth.login'))
        ->assertOk()
        ->assertDontSee('data-cookie-banner', false);
});
