<?php

namespace Database\Factories;

use App\Models\BankDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankDetail>
 */
class BankDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => null,
            'bank_name' => fake()->company().' Bank',
            'bank_address' => fake()->address(),
            'bsb' => fake()->numerify('###-###'),
            'swift_bic_code' => fake()->bothify('????AU??'),
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
        ];
    }
}
