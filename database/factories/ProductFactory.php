<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant,
            'laboratory_id' => Laboratory::factory()->for($tenant),
            'product_category_id' => ProductCategory::factory()->for($tenant),
            'active_ingredient_id' => ActiveIngredient::factory(),
            'minimum_unit_id' => ProductUnit::factory(),
            'internal_code' => fake()->unique()->bothify('PRD-####'),
            'barcode' => fake()->ean13(),
            'commercial_name' => fake()->words(3, true),
            'generic_name' => fake()->words(2, true),
            'cum' => fake()->unique()->numerify('########-#'),
            'health_registration' => fake()->bothify('INVIMA ####M-#####'),
            'pharmaceutical_form' => fake()->randomElement(['Tableta', 'Capsula', 'Jarabe', 'Suspension', 'Crema']),
            'concentration' => fake()->randomElement(['500mg', '250mg/5ml', '100mg', '1%', '50mg']),
            'purchase_price' => fake()->numberBetween(500, 80000),
            'sale_price' => fake()->numberBetween(1000, 120000),
            'regulated_price' => null,
            'tax_rate' => fake()->randomElement([0, 5, 19]),
            'requires_invima_registration' => true,
            'is_controlled' => false,
            'control_level' => null,
            'status' => ProductStatus::Active,
            'image_path' => null,
            'notes' => null,
        ];
    }

    public function controlled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_controlled' => true,
            'control_level' => 1,
        ]);
    }
}
