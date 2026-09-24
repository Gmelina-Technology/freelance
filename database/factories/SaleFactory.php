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
}
