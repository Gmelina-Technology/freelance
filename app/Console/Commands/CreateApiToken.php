<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    protected $signature = 'freelance:api-token {email} {--name=dara-aios}';

    protected $description = 'Create a Sanctum personal access token for a user (for the task API).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'));

        $this->info("Personal access token for {$user->email} (shown once):");
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Put this in dara/.secrets/freelance.json as "token". It cannot be retrieved again.');

        return self::SUCCESS;
    }
}
