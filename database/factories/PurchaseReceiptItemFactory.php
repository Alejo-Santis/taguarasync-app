<?php

namespace Database\Factories;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReceiptItem>
 */
class PurchaseReceiptItemFactory extends Factory
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
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->numberBetween(100, 10000);
        $subtotal = $quantity * $unitCost;

        return [
            'tenant_id' => $tenant,
            'purchase_receipt_id' => PurchaseReceipt::factory()->for($tenant),
            'product_id' => $product,
            'product_presentation_id' => $presentation,
            'inventory_lot_id' => InventoryLot::factory()->for($tenant)->for($product)->for($presentation, 'presentation'),
            'inventory_movement_id' => InventoryMovement::factory()->for($tenant)->for($product)->for($presentation, 'presentation'),
            'description' => fake()->words(3, true),
            'lot_number' => fake()->unique()->bothify('LOT-####'),
            'expires_on' => fake()->optional()->dateTimeBetween('+3 months', '+30 months')?->format('Y-m-d'),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'tax_rate' => 0,
            'line_subtotal' => $subtotal,
            'line_tax' => 0,
            'line_total' => $subtotal,
        ];
    }
}
