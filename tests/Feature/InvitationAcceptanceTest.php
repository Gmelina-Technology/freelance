<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * Signed accept link the way InvitationEmail builds it.
 */
function acceptUrl(Invitation $invitation): string
{
    return URL::temporarySignedRoute('invitations.accept', now()->addDays(7), ['token' => $invitation->token]);
}

function invite(Account $account, string $email, AccountRole $role = AccountRole::Manager): Invitation
{
    return Invitation::create([
        'account_id' => $account->id,
        'email' => $email,
        'role' => $role,
        'invited_by_id' => $account->owner_id,
    ]);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('creates a new invitee with an unknown password, a verified email and the invited role', function () {
    Notification::fake();
    $invitation = invite($this->account, 'new.person@example.com', AccountRole::Manager);

    $this->get(acceptUrl($invitation))->assertRedirect(route('filament.app.auth.login'));

    $user = User::where('email', 'new.person@example.com')->sole();

    expect(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getAccountRole($this->account))->toBe(AccountRole::Manager)
        ->and($invitation->fresh()->isAccepted())->toBeTrue();
});

it('gives every new invitee a different generated password', function () {
    Notification::fake();

    $this->get(acceptUrl(invite($this->account, 'first@example.com')));
    $this->get(acceptUrl(invite($this->account, 'second@example.com')));

    $first = User::where('email', 'first@example.com')->sole();
    $second = User::where('email', 'second@example.com')->sole();

    expect($first->password)->not->toBe($second->password)
        ->and(Hash::check('password', $second->password))->toBeFalse();
});

it('emails the new invitee a link to set their own password and does not log them in', function () {
    Notification::fake();
    $invitation = invite($this->account, 'new.person@example.com');

    $this->get(acceptUrl($invitation))->assertRedirect(route('filament.app.auth.login'));

    $user = User::where('email', 'new.person@example.com')->sole();

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        return str_starts_with($notification->url, route('filament.app.auth.password-reset.reset'))
            && str_contains($notification->url, 'email='.urlencode($user->email));
    });

    $this->assertGuest();
    auth()->logout();
    $this->get(route('filament.app.pages.dashboard', $this->account))->assertRedirect(route('filament.app.auth.login'));
});

it('does not log in an existing user who follows the link while logged out', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = invite($this->account, 'existing@example.com');
    $url = acceptUrl($invitation);

    $this->get($url)->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
    expect(session('url.intended'))->toBe($url)
        ->and($invitation->fresh()->isPending())->toBeTrue()
        ->and($existing->getAccountRole($this->account))->toBeNull();
});

it('does not log in a user who was created by an earlier invitation and has not set a password yet', function () {
    Notification::fake();
    $this->get(acceptUrl(invite($this->account, 'early@example.com')));

    $otherAccount = Account::factory()->for(User::factory(), 'owner')->create();
    $second = invite($otherAccount, 'early@example.com');

    $this->get(acceptUrl($second))->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
    expect($second->fresh()->isPending())->toBeTrue();
});

it('completes the invitation once the existing user logs in and returns to the link', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = invite($this->account, 'existing@example.com', AccountRole::Member);

    $url = acceptUrl($invitation);

    $this->get($url)->assertRedirect(route('filament.app.auth.login'));

    $afterLogin = app(LoginResponse::class)->toResponse(request());

    expect($afterLogin->getTargetUrl())->toBe($url);

    $this->actingAs($existing)
        ->get($afterLogin->getTargetUrl())
        ->assertRedirect(route('filament.app.pages.dashboard', $this->account));

    expect($invitation->fresh()->isAccepted())->toBeTrue()
        ->and($existing->getAccountRole($this->account))->toBe(AccountRole::Member);
});

it('sends a logged-out visitor of an already accepted link to the login page', function () {
    $user = User::factory()->create(['email' => 'accepted@example.com']);
    $invitation = invite($this->account, 'accepted@example.com');
    $invitation->accept();

    $this->get(acceptUrl($invitation))->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
});

it('accepts the invitation for a logged-in user with the matching email', function () {
    $user = User::factory()->create(['email' => 'member@example.com', 'email_verified_at' => null]);
    $invitation = invite($this->account, 'member@example.com', AccountRole::Member);

    $this->actingAs($user)
        ->get(acceptUrl($invitation))
        ->assertRedirect(route('filament.app.pages.dashboard', $this->account));

    expect($invitation->fresh()->isAccepted())->toBeTrue()
        ->and($user->getAccountRole($this->account))->toBe(AccountRole::Member)
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

it('refuses a logged-in user whose email does not match the invitation', function () {
    $invitation = invite($this->account, 'invited@example.com');
    $someoneElse = User::factory()->create();

    $this->actingAs($someoneElse)->get(acceptUrl($invitation))->assertForbidden();

    expect($invitation->fresh()->isPending())->toBeTrue()
        ->and($someoneElse->getAccountRole($this->account))->toBeNull();
});

it('refuses links that are not signed or were tampered with', function () {
    $invitation = invite($this->account, 'new.person@example.com');

    $this->get(route('invitations.accept', ['token' => $invitation->token]))->assertForbidden();
    $this->get(acceptUrl($invitation).'x')->assertForbidden();

    expect(User::where('email', 'new.person@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->isPending())->toBeTrue();
});

it('never logs anyone in from the invitation flow', function () {
    foreach (['Http/Controllers/InvitationAcceptanceController.php', 'Services/InvitationService.php'] as $file) {
        $source = file_get_contents(app_path($file));

        expect($source)->not->toContain('Auth::login')
            ->and($source)->not->toContain("Hash::make('password')");
    }
});
