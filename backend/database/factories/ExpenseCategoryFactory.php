<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
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
            'name' => fake()->unique()->randomElement(['Eau', 'Électricité', 'Gardiennage', 'Entretien', 'Assurance', 'Internet', 'Divers']),
        ];
    }
}
