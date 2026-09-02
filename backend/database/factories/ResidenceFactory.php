<?php

namespace Database\Factories;

use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Residence>
 */
class ResidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Résidence '.fake()->streetName(),
            'address' => fake()->address(),
            'lots_count' => fake()->numberBetween(6, 100),
            'bank_rib' => fake()->numerify('###780#################'),
        ];
    }
}
