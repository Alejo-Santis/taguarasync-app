<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'supplier_id' => Supplier::factory(),
            'bank_account_id' => null,
            'user_id' => null,
            'payment_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'amount' => fake()->numberBetween(10000, 5000000),
            'reference' => fake()->optional()->numerify('REF-######'),
            'notes' => null,
        ];
    }
}
