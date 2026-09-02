<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revenue>
 */
class RevenueFactory extends Factory
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
            'revenue_category_id' => RevenueCategory::factory(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'received_at' => now(),
            'label' => fake()->sentence(3),
            'amount' => fake()->numberBetween(50, 2000),
        ];
    }
}
