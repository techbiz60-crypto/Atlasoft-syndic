<?php

namespace Database\Factories;

use App\Models\FundCall;
use App\Models\Lot;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundCall>
 */
class FundCallFactory extends Factory
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
            'lot_id' => Lot::factory(),
            'amount' => fake()->numberBetween(100, 500),
            'period' => now()->startOfMonth(),
            'is_opening_balance' => false,
        ];
    }
}
