<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleConversion>
 */
class SaleConversionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $net = fake()->randomFloat(2, 10, 5000);

        return [
            'sale_id' => Sale::factory(),
            'converted_currency_code' => 'USD',
            'converted_amount' => $net,
            'fee_amount' => null,
            'tax_amount' => null,
            'gross_amount' => $net,
            'exchange_rate' => 1,
            'conversion_note' => null,
            'converted_at' => now(),
            'converted_by' => User::factory(),
        ];
    }

    public function withFeeAndTax(string $fee, string $tax): static
    {
        return $this->state(fn (array $attributes): array => [
            'fee_amount' => $fee,
            'tax_amount' => $tax,
            'gross_amount' => number_format((float) $attributes['converted_amount'] + (float) $fee + (float) $tax, 2, '.', ''),
        ]);
    }
}
