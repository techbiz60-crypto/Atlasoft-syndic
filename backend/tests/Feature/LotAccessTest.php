<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, string $ownerName = 'Mohamed Alami'): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create(['owner_name' => $ownerName]);
    }

    public function test_admin_can_grant_access_to_a_lots_owner(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'coproprietaire');
        $this->assertNotEmpty($response->json('generated_password'));

        $this->assertDatabaseHas('users', [
            'email' => 'mohamed@example.com',
            'lot_id' => $lot->id,
            'residence_id' => $residence->id,
            'role' => Role::Coproprietaire->value,
        ]);
    }

    public function test_a_granted_resident_account_can_actually_use_the_api(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ])->assertCreated();

        $resident = User::where('email', 'mohamed@example.com')->firstOrFail();

        // The syndic hands the password over directly, so the account must
        // be verified on creation — otherwise it clears login but every API
        // route (all behind the "verified" middleware) rejects it.
        $this->assertNotNull($resident->email_verified_at);

        $this->actingAs($resident)->getJson('/api/lots')->assertOk();
    }

    public function test_a_lot_cannot_be_granted_access_twice(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ])->assertCreated();

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed2@example.com',
        ])->assertStatus(422);
    }

    public function test_conseil_member_cannot_grant_access(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence);

        $this->actingAs($member)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ])->assertForbidden();
    }

    public function test_admin_can_revoke_access_via_the_generic_user_endpoint(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ])->assertCreated();

        $resident = $lot->fresh()->residentUser;

        $this->actingAs($admin)->deleteJson("/api/users/{$resident->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $resident->id]);
    }

    public function test_lots_index_exposes_whether_a_lot_already_has_access(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/access", [
            'name' => 'Mohamed Alami',
            'email' => 'mohamed@example.com',
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/lots');

        $response->assertOk()->assertJsonPath('data.0.resident_user.email', 'mohamed@example.com');
    }
}
