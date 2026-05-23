<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
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
            'name' => fake()->randomElement(['Efectivo', 'Transferencia bancaria', 'Tarjeta debito']),
            'code' => 'PM-'.fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(['cash', 'transfer', 'card']),
            'dian_payment_method_code' => fake()->randomElement(['10', '47', '48', '49']),
            'payment_form' => '1',
            'requires_reference' => false,
            'requires_bank_account' => false,
            'allows_attachment' => false,
            'affects_cash' => false,
            'is_active' => true,
            'sort_order' => 10,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Efectivo',
            'code' => 'cash',
            'type' => 'cash',
            'dian_payment_method_code' => '10',
            'affects_cash' => true,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Transferencia bancaria',
            'code' => 'transfer',
            'type' => 'transfer',
            'dian_payment_method_code' => '47',
            'requires_reference' => true,
            'requires_bank_account' => true,
            'allows_attachment' => true,
        ]);
    }
}
