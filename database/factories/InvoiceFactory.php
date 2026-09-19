<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
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
            'project_id' => null,
            'task_id' => null,
            'client_id' => Client::factory(),
            'number' => 'INV-'.str_pad((string) $this->faker->unique()->numberBetween(1000, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->numberBetween(100, 50000),
            'status' => InvoiceStatus::Draft,
            'issued_at' => $this->faker->dateTimeThisMonth(),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notes' => $this->faker->optional()->text(200),
        ];
    }
}
