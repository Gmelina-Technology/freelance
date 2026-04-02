<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'client_id' => Client::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['pending', 'active', 'completed']),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+90 days'),
        ];
    }
}
