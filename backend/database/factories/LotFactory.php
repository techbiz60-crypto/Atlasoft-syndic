<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'building_id' => Building::factory(),
            'lot_type_id' => LotType::factory(),
            // "Lot ##" (100 combinations) collided often enough across a
            // full test run to intermittently break the real unique
            // constraint on (building_id, number) — widened well past what
            // the whole suite could plausibly exhaust.
            'number' => strtoupper(fake()->unique()->bothify('Lot-####')),
            'owner_name' => fake()->name(),
            'owner_phone' => fake()->numerify('+2126########'),
            'owner_email' => fake()->safeEmail(),
        ];
    }
}
