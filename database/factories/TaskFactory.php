<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['open', 'in_progress', 'completed']),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+60 days'),
            'assigned_user_id' => User::factory(),
        ];
    }
}
