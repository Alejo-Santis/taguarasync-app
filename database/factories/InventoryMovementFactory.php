<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
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
        $presentation = ProductPresentation::factory()->for($tenant)->for($product);
        $lot = InventoryLot::factory()
            ->for($tenant)
            ->for($product)
            ->for($presentation, 'presentation');

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant,
            'inventory_lot_id' => $lot,
            'product_id' => $product,
            'product_presentation_id' => $presentation,
            'user_id' => User::factory()->for($tenant),
            'type' => InventoryMovementType::Opening,
            'quantity_delta' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'unit_cost' => 100,
            'reference_type' => null,
            'reference_id' => null,
            'reference_code' => null,
            'notes' => null,
            'occurred_at' => now(),
        ];
    }
}
