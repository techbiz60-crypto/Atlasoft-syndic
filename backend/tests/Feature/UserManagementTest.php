<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_tresorier_account(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Fatima Idrissi',
            'email' => 'fatima@example.com',
            'role' => Role::Tresorier->value,
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'tresorier');
        $this->assertNotEmpty($response->json('generated_password'));
        $this->assertDatabaseHas('users', ['email' => 'fatima@example.com', 'residence_id' => $residence->id]);
    }

    public function test_admin_can_create_a_conseil_account(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
            'role' => Role::Conseil->value,
        ])->assertCreated();
    }

    public function test_a_created_account_can_actually_use_the_api(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Fatima Idrissi',
            'email' => 'fatima@example.com',
            'role' => Role::Tresorier->value,
        ])->assertCreated();

        $tresorier = User::where('email', 'fatima@example.com')->firstOrFail();

        // The admin hands the password over directly, so the account must be
        // verified on creation — otherwise it clears login but every API
        // route (all behind the "verified" middleware) rejects it.
        $this->assertNotNull($tresorier->email_verified_at);

        $this->actingAs($tresorier)->getJson('/api/lots')->assertOk();
    }

    public function test_cannot_create_a_user_with_admin_role_via_this_endpoint(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nouvel Admin',
            'email' => 'admin2@example.com',
            'role' => Role::Admin->value,
        ])->assertStatus(422);
    }

    public function test_conseil_member_cannot_create_users(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->postJson('/api/users', [
            'name' => 'Fatima Idrissi',
            'email' => 'fatima@example.com',
            'role' => Role::Tresorier->value,
        ])->assertForbidden();
    }

    public function test_admin_only_sees_users_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        User::factory()->for($residenceB)->conseil()->create();

        $response = $this->actingAs($adminA)->getJson('/api/users');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_delete_a_tresorier_account(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $tresorier = User::factory()->for($residence)->tresorier()->create();

        $this->actingAs($admin)->deleteJson("/api/users/{$tresorier->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $tresorier->id]);
    }

    public function test_admin_cannot_delete_the_admin_account(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $otherAdmin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->deleteJson("/api/users/{$otherAdmin->id}")->assertStatus(422);
    }

    public function test_admin_cannot_delete_a_user_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $memberB = User::factory()->for($residenceB)->conseil()->create();

        $this->actingAs($adminA)->deleteJson("/api/users/{$memberB->id}")->assertNotFound();
    }
}
