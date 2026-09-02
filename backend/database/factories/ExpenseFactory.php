<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Residence;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'expense_category_id' => ExpenseCategory::factory(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'paid_at' => now(),
            'label' => fake()->sentence(3),
            'amount' => fake()->numberBetween(100, 3000),
        ];
    }
}
