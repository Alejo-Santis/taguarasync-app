<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccountMovement>
 */
class BankAccountMovementFactory extends Factory
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
            'bank_account_id' => BankAccount::factory(),
            'user_id' => User::factory(),
            'type' => 'inflow',
            'amount' => fake()->numberBetween(1000, 200000),
            'reference' => fake()->bothify('TRX-####'),
            'status' => 'pending',
            'occurred_at' => now(),
            'description' => fake()->sentence(),
        ];
    }
}
