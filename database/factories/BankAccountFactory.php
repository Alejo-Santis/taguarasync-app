<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'bank_name' => fake()->randomElement(['Bancolombia', 'Davivienda', 'Nequi']),
            'account_name' => fake()->company(),
            'account_number' => fake()->numerify('##########'),
            'type' => fake()->randomElement(['savings', 'checking', 'wallet']),
            'is_default' => false,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
