<?php

namespace Database\Factories;

use App\Enums\ProductUnitKind;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Unidad', 'Tableta', 'Capsula', 'Frasco', 'Tubo', 'Ampolla', 'Sobre', 'Blister', 'Caja']);

        return [
            'name' => $name,
            'code' => Str::slug($name),
            'kind' => ProductUnitKind::Minimum,
            'allows_decimals' => false,
            'is_active' => true,
        ];
    }
}
