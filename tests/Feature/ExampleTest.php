<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('guests see log in and sign up links on the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('filament.app.auth.login'), false)
        ->assertSee(route('filament.app.auth.register'), false)
        ->assertDontSee('/dashboard');
});

test('authenticated users are pointed at the app from the home page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Open your workspace')
        ->assertDontSee('Create a free account');
});
