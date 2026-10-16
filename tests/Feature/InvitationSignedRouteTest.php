<?php

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->account = Account::factory()->for(User::factory()->create(), 'owner')->create();
});

/**
 * Title and body of the last queued notification.
 *
 * @return array{title: string, body: string}
 */
function lastNotice(): array
{
    $notice = collect(session('filament.notifications'))->last();

    return ['title' => $notice['title'], 'body' => $notice['body']];
}

it('enforces the signature with the signed middleware on the accept route', function () {
    $middleware = Route::getRoutes()->getByName('invitations.accept')->gatherMiddleware();

    expect($middleware)->toContain('signed')
        ->and($middleware)->not->toContain('signed:relative');
});

it('gives a tampered link and a signed unknown token the same response', function () {
    $invitation = Invitation::factory()->for($this->account)->create();

    $tampered = $this->get(URL::temporarySignedRoute('invitations.accept', now()->addDay(), ['token' => $invitation->token]).'x');
    $tamperedNotice = lastNotice();

    $unknown = $this->get(URL::temporarySignedRoute('invitations.accept', now()->addDay(), ['token' => 'no-such-token']));

    expect($tampered->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($tampered->getTargetUrl())->toBe($unknown->getTargetUrl())
        ->and(lastNotice())->toBe($tamperedNotice)
        ->and($invitation->fresh()->isPending())->toBeTrue();
});

it('keeps the default 403 for signed routes outside the invitation flow', function () {
    $this->get(route('filament.app.auth.password-reset.reset', ['email' => 'someone@example.com', 'token' => 'abc']))
        ->assertForbidden();

    expect(collect(session('filament.notifications'))->pluck('title')->all())
        ->not->toContain('Invitation expired or no longer valid');
});
