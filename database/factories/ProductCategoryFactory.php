<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Analgesicos', 'Antibioticos', 'Antigripales', 'Dermatologicos', 'Gastrointestinales', 'Vitaminas']);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
