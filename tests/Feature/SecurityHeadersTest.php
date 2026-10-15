<?php

use App\Models\Account;
use App\Models\CommentAttachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function baselineSecurityHeaders(): array
{
    return [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];
}

it('sets the security headers on a web page', function () {
    $response = $this->get('/terms')->assertOk();

    foreach (baselineSecurityHeaders() as $name => $value) {
        $response->assertHeader($name, $value);
    }

    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("frame-ancestors 'self'")
        ->toContain("'unsafe-eval'")
        ->toContain("object-src 'none'");
});

it('sets the security headers on an API JSON response', function () {
    $owner = User::factory()->create();
    Account::factory()->for($owner, 'owner')->create();
    actingAsToken($owner);

    $response = $this->getJson('/api/tasks')->assertOk();

    foreach (baselineSecurityHeaders() as $name => $value) {
        $response->assertHeader($name, $value);
    }

    $response->assertHeader('Content-Security-Policy-Report-Only');
});

it('does not send an enforcing Content-Security-Policy', function () {
    $this->get('/terms')->assertHeaderMissing('Content-Security-Policy');
});

it('can turn the CSP report-only header off', function () {
    config(['security.csp.enabled' => false]);

    $this->get('/terms')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('can turn every security header off', function () {
    config(['security.headers.enabled' => false]);

    $this->get('/terms')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('sends HSTS in production over HTTPS only', function () {
    $this->app['env'] = 'production';

    $this->get('https://localhost/terms')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('does not send HSTS over plain HTTP in production', function () {
    $this->app['env'] = 'production';

    $this->get('http://localhost/terms')->assertHeaderMissing('Strict-Transport-Security');
});

it('does not send HSTS outside production', function () {
    $this->get('https://localhost/terms')->assertHeaderMissing('Strict-Transport-Security');
});

it('keeps a header the controller already set', function () {
    Route::get('/_test/framing', fn () => response('ok')->header('X-Frame-Options', 'DENY'));

    $this->get('/_test/framing')->assertHeader('X-Frame-Options', 'DENY');
});

it('serves attachment downloads with nosniff as an attachment', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($account)->create(['client_id' => null, 'project_id' => null]);
    $comment = $task->comments()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'body' => 'File attached.',
        'body_format' => 'rich',
    ]);
    $attachment = CommentAttachment::factory()->for($comment)->create(['disk' => 'local', 'path' => 'comments/note.txt']);
    Storage::disk('local')->put($attachment->path, 'hello');

    $response = $this->actingAs($owner)->get(route('comments.attachments.download', $attachment))->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});
