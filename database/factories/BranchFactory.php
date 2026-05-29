<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
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
            'name' => fake()->city().' '.fake()->randomElement(['Principal', 'Norte', 'Sur', 'Centro']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_main' => false,
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(['is_main' => true]);
    }
}
