<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    $this->get(route('invitations.accept', ['token' => $invitation->token]))->assertRedirect(route('filament.app.auth.login'));
    $this->get(acceptUrl($invitation).'x')->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
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

/**
 * Assert the warning every unusable link ends in is queued for the login page.
 */
function expectInvalidInvitationNotice(): void
{
    $titles = collect(session('filament.notifications'))->pluck('title')->all();

    expect($titles)->toContain('Invitation expired or no longer valid');
}

it('sends every unusable link to the login page with the same warning and changes nothing', function (Closure $makeUrl) {
    $invitation = invite($this->account, 'new.person@example.com');

    $this->get($makeUrl($invitation))->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
    expectInvalidInvitationNotice();
    expect(User::where('email', 'new.person@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->isPending())->toBeTrue();
})->with([
    'expired column with a valid signature' => [function (Invitation $invitation): string {
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        return acceptUrl($invitation);
    }],
    'unsigned link' => [fn (Invitation $invitation): string => route('invitations.accept', ['token' => $invitation->token])],
    'tampered link' => [fn (Invitation $invitation): string => acceptUrl($invitation).'x'],
    'expired signature' => [function (Invitation $invitation): string {
        $url = acceptUrl($invitation);
        test()->travel(8)->days();

        return $url;
    }],
]);

it('gives an unknown or revoked token the same outcome as a bad signature', function () {
    $invitation = invite($this->account, 'new.person@example.com');
    $revokedUrl = acceptUrl($invitation);
    $invitation->delete();

    $unknownUrl = URL::temporarySignedRoute('invitations.accept', now()->addDays(7), ['token' => 'no-such-token']);

    $this->get($revokedUrl)->assertRedirect(route('filament.app.auth.login'));
    expectInvalidInvitationNotice();

    $this->get($unknownUrl)->assertRedirect(route('filament.app.auth.login'));
    $this->get(route('invitations.accept', ['token' => 'no-such-token']))->assertRedirect(route('filament.app.auth.login'));
    $this->assertGuest();
});

it('checks the signature before looking the invitation up', function () {
    $invitation = invite($this->account, 'new.person@example.com');

    $unsignedKnown = $this->get(route('invitations.accept', ['token' => $invitation->token]));
    $unsignedUnknown = $this->get(route('invitations.accept', ['token' => 'no-such-token']));

    expect($unsignedKnown->getTargetUrl())->toBe($unsignedUnknown->getTargetUrl())
        ->and($unsignedKnown->getStatusCode())->toBe($unsignedUnknown->getStatusCode());
});

it('still shows the accepted notice for an expired invitation that was already accepted', function () {
    $invitation = Invitation::factory()->for($this->account)->accepted()->expired()->create(['email' => 'done@example.com']);

    $this->get(acceptUrl($invitation))->assertRedirect(route('filament.app.auth.login'));

    $titles = collect(session('filament.notifications'))->pluck('title')->all();

    expect($titles)->toContain('Invitation already accepted')
        ->and($titles)->not->toContain('Invitation expired or no longer valid');
});

it('gives new invitations the configured expiry and accepts them until then', function () {
    Notification::fake();
    $this->travelTo(now()->startOfSecond());
    $invitation = invite($this->account, 'fresh@example.com');

    expect($invitation->expires_at->equalTo(now()->addDays(7)))->toBeTrue()
        ->and($invitation->isExpired())->toBeFalse();

    $url = URL::temporarySignedRoute('invitations.accept', $invitation->expires_at, ['token' => $invitation->token]);

    $this->travel(6)->days();
    $this->get($url)->assertRedirect(route('filament.app.auth.login'));

    expect($invitation->fresh()->isAccepted())->toBeTrue();
});

it('refuses to accept an expired pending invitation at the service level', function () {
    $invitation = Invitation::factory()->for($this->account)->expired()->create(['email' => 'late@example.com']);
    $user = User::factory()->create(['email' => 'late@example.com']);
    $newInvitee = Invitation::factory()->for($this->account)->expired()->create();

    expect(fn () => app(InvitationService::class)->acceptForUser($invitation, $user))
        ->toThrow(DomainException::class, 'expired')
        ->and(fn () => app(InvitationService::class)->acceptAsNewUser($newInvitee))
        ->toThrow(DomainException::class, 'expired');

    expect($invitation->fresh()->isPending())->toBeTrue()
        ->and($user->getAccountRole($this->account))->toBeNull();
});

function runInvitationExpiryBackfill(): void
{
    (require database_path('migrations/2026_10_15_000002_backfill_invitations_expires_at.php'))->up();
}

it('backfills expiry for invitations created before it existed and leaves the rest alone', function () {
    $legacy = Invitation::factory()->for($this->account)->create();
    DB::table('invitations')->where('id', $legacy->id)->update(['expires_at' => null, 'created_at' => '2026-10-01 10:00:00']);

    $noCreatedAt = Invitation::factory()->for($this->account)->create();
    DB::table('invitations')->where('id', $noCreatedAt->id)->update(['expires_at' => null, 'created_at' => null]);

    $filled = Invitation::factory()->for($this->account)->create(['expires_at' => '2027-01-01 00:00:00']);

    runInvitationExpiryBackfill();
    runInvitationExpiryBackfill();

    expect($legacy->fresh()->expires_at->toDateTimeString())->toBe('2026-10-08 10:00:00')
        ->and($noCreatedAt->fresh()->expires_at)->not->toBeNull()
        ->and($filled->fresh()->expires_at->toDateTimeString())->toBe('2027-01-01 00:00:00');
});
