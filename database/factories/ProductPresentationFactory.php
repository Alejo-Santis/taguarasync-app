<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPresentation>
 */
class ProductPresentationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'product_id' => Product::factory()->for($tenant),
            'unit_id' => ProductUnit::factory(),
            'name' => fake()->randomElement(['Unidad', 'Blister x 10', 'Caja x 100', 'Frasco 60ml', 'Tubo']),
            'barcode' => fake()->optional()->ean13(),
            'minimum_unit_quantity' => fake()->randomElement([1, 10, 20, 30, 100]),
            'sale_price' => fake()->numberBetween(1000, 180000),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
