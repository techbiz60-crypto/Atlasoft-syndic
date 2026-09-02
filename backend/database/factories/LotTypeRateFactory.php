<?php

namespace Database\Factories;

use App\Models\LotType;
use App\Models\LotTypeRate;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotTypeRate>
 */
class LotTypeRateFactory extends Factory
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
            'lot_type_id' => LotType::factory(),
            'amount' => fake()->numberBetween(100, 500),
            'effective_date' => now()->startOfYear(),
        ];
    }
}
