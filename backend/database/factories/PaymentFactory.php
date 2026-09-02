<?php

namespace Database\Factories;

use App\Models\FundCall;
use App\Models\Payment;
use App\Models\Residence;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'fund_call_id' => FundCall::factory(),
            'amount' => fake()->numberBetween(50, 500),
            'paid_at' => now(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }
}
