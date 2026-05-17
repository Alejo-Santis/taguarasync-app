<?php

namespace Database\Factories;

use App\Models\ActiveIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActiveIngredient>
 */
class ActiveIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dci_name' => fake()->unique()->randomElement(['Acetaminofen', 'Ibuprofeno', 'Loratadina', 'Amoxicilina', 'Omeprazol', 'Losartan']),
            'pharmacological_group' => fake()->randomElement(['Analgesico', 'Antibiotico', 'Antihistaminico', 'Gastroprotector', 'Antihipertensivo']),
            'atc_classification' => fake()->bothify('?#??##'),
        ];
    }
}
