<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
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
            'number' => 'QTE-'.$this->faker->unique()->numberBetween(1000, 9999),
            'amount' => $this->faker->numberBetween(100, 50000),
            'status' => QuoteStatus::Draft,
            'issued_at' => $this->faker->dateTimeThisMonth(),
            'valid_until' => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Draft,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Sent,
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Converted,
            'invoice_ref' => $this->faker->numerify('###-#######-###'),
        ]);
    }
}
