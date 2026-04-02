<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
