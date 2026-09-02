<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\User;
use App\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'role' => Role::Admin,
            'is_platform_admin' => false,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function conseil(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Conseil,
        ]);
    }

    public function tresorier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Tresorier,
        ]);
    }

    public function coproprietaire(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Coproprietaire,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'residence_id' => null,
            'is_platform_admin' => true,
        ]);
    }
}
