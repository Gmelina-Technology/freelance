<?php

use App\Filament\App\Pages\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

test('terms and privacy pages are publicly reachable', function (string $routeName, string $heading) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee($heading);
})->with([
    'terms' => ['terms', 'Terms of Service'],
    'privacy' => ['privacy', 'Privacy Policy'],
]);

test('legal pages show the configured contact email', function () {
    config(['legal.contact_email' => 'legal@example.test']);

    $this->get(route('privacy'))->assertSee('legal@example.test');
});

test('the home page footer links to the legal pages', function () {
    $this->get('/')
        ->assertSee(route('terms'), false)
        ->assertSee(route('privacy'), false);
});

test('registration requires accepting the terms', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'password' => 'password-1234',
            'passwordConfirmation' => 'password-1234',
            'terms' => false,
        ])
        ->call('register')
        ->assertHasFormErrors(['terms' => 'accepted']);

    expect(User::where('email', 'jane@example.test')->exists())->toBeFalse();
});

test('registration records when the terms were accepted', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'password' => 'password-1234',
            'passwordConfirmation' => 'password-1234',
            'terms' => true,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'jane@example.test')->first()->terms_accepted_at)->not->toBeNull();
});
