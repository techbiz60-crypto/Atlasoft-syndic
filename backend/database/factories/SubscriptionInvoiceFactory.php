<?php

namespace Database\Factories;

use App\BillingCycle;
use App\Models\Residence;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
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
            'subscription_id' => Subscription::factory(),
            'plan' => SubscriptionPlan::Starter,
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 50,
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'validated_at' => now(),
        ];
    }
}
