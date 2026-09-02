<?php

namespace Database\Factories;

use App\Models\LotType;
use App\Models\LotTypeRate;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotType>
 */
class LotTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'name' => fake()->unique()->randomElement(['Standard', 'Studio', 'Duplex', 'T1', 'T2', 'T3', 'Loft', 'Commerce']),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (LotType $lotType) {
            if ($lotType->rates()->doesntExist()) {
                LotTypeRate::factory()->create([
                    'residence_id' => $lotType->residence_id,
                    'lot_type_id' => $lotType->id,
                    'amount' => fake()->numberBetween(100, 500),
                    'effective_date' => now()->subYear(),
                ]);
            }
        });
    }

    /**
     * Attach a specific initial rate instead of a random one.
     */
    public function withMonthlyAmount(int $amount): static
    {
        return $this->afterCreating(function (LotType $lotType) use ($amount) {
            $lotType->rates()->delete();

            LotTypeRate::factory()->create([
                'residence_id' => $lotType->residence_id,
                'lot_type_id' => $lotType->id,
                'amount' => $amount,
                'effective_date' => now()->subYear(),
            ]);
        });
    }
}
