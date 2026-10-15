<?php

namespace Database\Factories;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => AccountRole::Member,
        ];
    }

    public function role(AccountRole $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => ['accepted_at' => now()]);
    }
}
