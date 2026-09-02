<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\RevenueCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueCategory>
 */
class RevenueCategoryFactory extends Factory
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
            'name' => fake()->unique()->randomElement(['Vente de biens/services', 'Location', 'Pénalités de retard', 'Divers']),
        ];
    }
}
