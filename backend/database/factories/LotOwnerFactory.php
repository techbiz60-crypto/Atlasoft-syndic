<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\LotOwner;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotOwner>
 */
class LotOwnerFactory extends Factory
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
            'owner_name' => fake()->name(),
            'owner_phone' => fake()->phoneNumber(),
            'owner_email' => fake()->safeEmail(),
            'started_at' => now(),
        ];
    }
}
