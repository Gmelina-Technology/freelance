<?php

namespace App\Console\Commands;

use App\Filament\App\Pages\ApiTokens;
use App\Models\User;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    protected $signature = 'freelance:api-token {email} {account : ID of the account the token is bound to} {--name=dara-aios} {--expires-in-days= : 30, 90, 365 or "never"; defaults to the configured expiry}';

    protected $description = 'Create a Sanctum personal access token for a user (for the task API).';

    public function handle(): int
    {
        $expiresIn = $this->option('expires-in-days');

        if ($expiresIn !== null && ! in_array($expiresIn, ['30', '90', '365', 'never'], true)) {
            $this->error('--expires-in-days must be 30, 90, 365 or never.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $account = $user->accounts()->whereKey($this->argument('account'))->first()
            ?? $user->ownedAccounts()->whereKey($this->argument('account'))->first();

        if (! $account) {
            $this->error("{$user->email} does not belong to account {$this->argument('account')}.");

            return self::FAILURE;
        }

        $expiresIn = $this->option('expires-in-days') ?? ApiTokens::defaultExpiryOption();
        $expiresAt = ApiTokens::expiryFromOption($expiresIn);

        $token = $user->createAccountToken($account, $this->option('name'), ['*'], $expiresAt);

        $this->info("Personal access token for {$user->email} on account {$account->getKey()} (shown once):");
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line($expiresAt ? 'Expires '.$expiresAt->toDateString().'.' : 'Never expires.');
        $this->comment('Put this in dara/.secrets/freelance.json as "token". It cannot be retrieved again.');

        return self::SUCCESS;
    }
}
