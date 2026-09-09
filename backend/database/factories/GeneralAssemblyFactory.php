<?php

namespace Database\Factories;

use App\Models\GeneralAssembly;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneralAssembly>
 */
class GeneralAssemblyFactory extends Factory
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
            'exercise_year' => fake()->year(),
            'held_on' => fake()->dateTimeBetween('now', '+2 months'),
        ];
    }
}
