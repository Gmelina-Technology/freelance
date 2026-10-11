<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
            'address' => fake()->address(),
            'email' => fake()->companyEmail(),
        ];
    }

    /**
     * An account with its currency set. The currency row is created if missing because
     * accounts.currency_code references currencies.code.
     */
    public function withCurrency(string $code = 'USD'): static
    {
        return $this->state(function () use ($code): array {
            Currency::firstOrCreate(['code' => $code], ['name' => $code]);

            return ['currency_code' => $code];
        });
    }
}
