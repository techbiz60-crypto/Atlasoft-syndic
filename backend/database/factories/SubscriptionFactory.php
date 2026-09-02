<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\Subscription;
use App\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'plan' => SubscriptionPlan::Starter,
            'billing_cycle' => null,
            'trial_ends_at' => now()->addDays(15),
            'current_period_end' => null,
        ];
    }

    public function trial(): static
    {
        return $this->state(['trial_ends_at' => now()->addDays(15), 'current_period_end' => null]);
    }

    public function expiredTrial(): static
    {
        return $this->state(['trial_ends_at' => now()->subDay(), 'current_period_end' => null]);
    }

    public function active(): static
    {
        return $this->state(['trial_ends_at' => null, 'current_period_end' => now()->addMonth()]);
    }

    public function expired(): static
    {
        return $this->state(['trial_ends_at' => null, 'current_period_end' => now()->subDay()]);
    }

    public function free(): static
    {
        return $this->state(['plan' => SubscriptionPlan::Free, 'trial_ends_at' => null, 'current_period_end' => null]);
    }
}
