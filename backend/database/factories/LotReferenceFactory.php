<?php

namespace Database\Factories;

use App\LotReferenceType;
use App\Models\Lot;
use App\Models\LotReference;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotReference>
 */
class LotReferenceFactory extends Factory
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
            'type' => LotReferenceType::ElevatorChip,
            'value' => fake()->unique()->numerify('####'),
        ];
    }
}
