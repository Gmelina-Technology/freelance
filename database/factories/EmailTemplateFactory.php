<?php

namespace Database\Factories;

use App\Enums\EmailTemplateType;
use App\Models\Account;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
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
            'type' => EmailTemplateType::QUOTE_REQUEST,
            'subject' => fake()->sentence(3),
            'body' => '<p>'.fake()->sentence().'</p>',
            'metadata' => null,
        ];
    }
}
