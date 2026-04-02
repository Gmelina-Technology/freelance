<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationAcceptanceController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        $user = $request->user();
        $invitedUser = User::firstWhere('email', $invitation->email);

        if ($user && $user->email !== $invitation->email) {
            abort(403);
        }

        if (! $user) {
            if (! $invitedUser) {
                $invitedUser = User::create([
                    'name' => Str::title(Str::before($invitation->email, '@')),
                    'email' => $invitation->email,
                    'password' => Hash::make('password'),
                ]);
            }

            Auth::login($invitedUser);
            $user = $invitedUser;
        }

        if ($invitation->isAccepted()) {
            Notification::make()
                ->warning()
                ->title('Invitation already accepted')
                ->body('This invitation has already been accepted.')
                ->send();

            return redirect()->route('filament.app.pages.dashboard', $invitation->account);
        }

        $invitation->accept();

        $account = $invitation->account;

        if (! $account->users()->where('user_id', $user->getKey())->exists()) {
            $account->users()->attach($user->getKey(), [
                'role' => $invitation->role,
            ]);
        }

        Notification::make()
            ->success()
            ->title('Invitation accepted')
            ->body('You have been added to the account.')
            ->send();

        return redirect()->route('filament.app.pages.dashboard', $invitation->account);
    }
}
