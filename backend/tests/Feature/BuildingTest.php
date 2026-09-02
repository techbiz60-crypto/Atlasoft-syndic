<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/buildings', [
            'name' => 'Sahil Ouad 5',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Sahil Ouad 5');
        $this->assertDatabaseHas('buildings', ['name' => 'Sahil Ouad 5', 'residence_id' => $residence->id]);
    }

    public function test_conseil_member_cannot_create_a_building(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->postJson('/api/buildings', [
            'name' => 'Sahil Ouad 5',
        ])->assertForbidden();
    }

    public function test_building_name_must_be_unique_within_the_same_residence(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        Building::factory()->for($residence)->create(['name' => 'Sahil Ouad 5']);

        $this->actingAs($admin)->postJson('/api/buildings', [
            'name' => 'Sahil Ouad 5',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_only_sees_buildings_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        Building::factory()->for($residenceA)->create(['name' => 'Sahil Ouad 5']);
        Building::factory()->for($residenceB)->create(['name' => 'Sahil Ouad 6']);

        $adminA = User::factory()->for($residenceA)->create();

        $this->actingAs($adminA)->getJson('/api/buildings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sahil Ouad 5');
    }

    public function test_admin_can_update_and_delete_a_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();

        $this->actingAs($admin)
            ->putJson("/api/buildings/{$building->id}", ['name' => 'Sahil Ouad 7'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sahil Ouad 7');

        $this->actingAs($admin)
            ->deleteJson("/api/buildings/{$building->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('buildings', ['id' => $building->id]);
    }

    public function test_admin_cannot_update_a_building_belonging_to_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $buildingB = Building::factory()->for($residenceB)->create();

        $this->actingAs($adminA)
            ->putJson("/api/buildings/{$buildingB->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }
}
