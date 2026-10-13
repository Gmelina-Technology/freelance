<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the task priority column from the migrations alone', function () {
    expect(Schema::hasColumn('tasks', 'priority'))->toBeTrue();
});

it('has no migrate route for guests or signed-in owners', function () {
    $scenario = makeQuoteScenario();

    $this->get('/migrate')->assertNotFound();

    $this->actingAs($scenario['owner'])->get('/migrate')->assertNotFound();
});

it('has no temporary invoice preview routes', function () {
    expect(Route::has('temp.invoice.preview'))->toBeFalse()
        ->and(Route::has('temp.invoice.render'))->toBeFalse();
});

it('serves nothing under the temporary invoice preview paths', function (string $suffix) {
    $scenario = makeQuoteScenario();
    $invoice = Invoice::factory()->create(['account_id' => $scenario['account']->id]);
    $stranger = User::factory()->create();

    $url = "/temp/invoices/{$invoice->id}/{$suffix}";

    $this->get($url)->assertNotFound();
    $this->actingAs($stranger)->get($url)->assertNotFound();
    $this->actingAs($scenario['owner'])->get($url)->assertNotFound();
})->with(['preview', 'render']);
