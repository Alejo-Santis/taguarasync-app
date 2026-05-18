<?php

namespace Database\Factories;

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryLot>
 */
class InventoryLotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();
        $product = Product::factory()->for($tenant);

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant,
            'product_id' => $product,
            'product_presentation_id' => ProductPresentation::factory()->for($tenant)->for($product),
            'lot_number' => fake()->unique()->bothify('LOT-####'),
            'expires_on' => fake()->dateTimeBetween('+3 months', '+30 months')->format('Y-m-d'),
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'unit_cost' => fake()->numberBetween(100, 80000),
            'status' => InventoryLotStatus::Available,
            'received_at' => now(),
            'notes' => null,
        ];
    }
}
