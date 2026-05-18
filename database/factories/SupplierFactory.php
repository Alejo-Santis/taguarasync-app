<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'nit' => fake()->optional()->numerify('#########-#'),
            'contact_name' => fake()->optional()->name(),
            'contact_email' => fake()->optional()->safeEmail(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
