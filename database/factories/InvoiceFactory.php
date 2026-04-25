<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'project_id' => Project::factory(),
            'task_id' => Task::factory(),
            'client_id' => Client::factory(),
            'invoice_number' => 'INV-'.str_pad($this->faker->unique()->numberBetween(1000, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->numberBetween(100, 50000),
            'status' => $this->faker->randomElement(['draft', 'sent', 'paid', 'overdue', 'cancelled']),
            'issued_at' => $this->faker->dateTimeThisMonth(),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notes' => $this->faker->optional()->text(200),
        ];
    }
}
