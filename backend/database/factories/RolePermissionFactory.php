<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Residence;
use App\Models\RolePermission;
use App\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
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
            'role' => Role::Tresorier,
            'permission_id' => fn () => Permission::query()->inRandomOrder()->value('id'),
        ];
    }
}
