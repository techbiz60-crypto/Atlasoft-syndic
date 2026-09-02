<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'name' => 'Bâtiment '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
