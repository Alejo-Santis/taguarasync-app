<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
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
            'sale_id' => Sale::query()->inRandomOrder()->value('id') ?? 1,
            'payment_method_id' => PaymentMethod::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(1000, 200000),
            'status' => 'confirmed',
            'paid_at' => now(),
        ];
    }
}
