<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A user that has switched on email multi-factor authentication.
 */
function userWithEmailMfa(string $email): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->toggleEmailAuthentication(true);

    return $user;
}

it('lets an allowlisted user with email MFA into the admin panel, ignoring case and spaces', function () {
    config(['app.admin_emails' => ' Admin@Example.com , b@x.io']);
    $admin = userWithEmailMfa('admin@example.com');

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('accepts the allowlist as an array too', function () {
    config(['app.admin_emails' => ['a@x.io', 'b@x.io,c@x.io']]);

    expect(userWithEmailMfa('c@x.io')->isPlatformAdmin())->toBeTrue();
});

it('always lets users into the app panel', function () {
    config(['app.admin_emails' => '']);
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('app')))->toBeTrue();
});

it('refuses everyone when the allowlist is empty, without a type error', function (mixed $configured) {
    config(['app.admin_emails' => $configured]);
    $user = userWithEmailMfa('admin@example.com');

    expect($user->isPlatformAdmin())->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($user)->get('/admin')->assertForbidden();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty array' => [[]],
]);

it('refuses an allowlisted user who has not enabled email MFA', function () {
    config(['app.admin_emails' => 'admin@example.com']);
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});

it('refuses a user with email MFA who is not on the allowlist', function () {
    config(['app.admin_emails' => 'admin@example.com']);
    $user = userWithEmailMfa('someone@example.com');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('sends guests to the app login', function () {
    $this->get('/admin')->assertRedirect(route('filament.app.auth.login'));
});
