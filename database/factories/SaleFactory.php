<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
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
            'category' => 'Service',
            'transaction_date' => now(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'reference_key' => null,
            'currency_code' => null,
        ];
    }

    /**
     * A sale recorded against an invoice, the way InvoiceService writes it.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (): array => [
            'account_id' => $invoice->account_id,
            'reference_key' => $invoice->number,
            'amount' => $invoice->amount,
        ]);
    }

    /**
     * A sale converted into $currencyCode. Net defaults to the original amount (1:1).
     */
    public function converted(string $currencyCode = 'USD', ?float $net = null): static
    {
        return $this->state(fn (): array => [
            'currency_code' => $currencyCode,
            'converted_currency_code' => $currencyCode,
            'converted_amount' => fn (array $attributes) => $net ?? $attributes['amount'],
            'gross_amount' => fn (array $attributes) => $net ?? $attributes['amount'],
            'exchange_rate' => 1,
            'converted_at' => now(),
        ]);
    }

    public function unconverted(?string $currencyCode = null): static
    {
        return $this->state(fn (): array => [
            'currency_code' => $currencyCode,
            'converted_currency_code' => null,
            'converted_amount' => null,
            'gross_amount' => null,
            'exchange_rate' => null,
            'converted_at' => null,
        ]);
    }
}
