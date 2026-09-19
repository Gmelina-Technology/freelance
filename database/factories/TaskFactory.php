<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\QuoteItem;
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

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::COMPLETED,
        ]);
    }

    public function forQuoteItem(QuoteItem $item): static
    {
        return $this->state(fn (): array => [
            'quote_item_id' => $item->getKey(),
            'account_id' => $item->quote->account_id,
            'client_id' => $item->quote->client_id,
            'project_id' => $item->quote->project_id,
            'title' => $item->title,
            'description' => $item->description,
            'category_id' => $item->category_id,
        ]);
    }
}
