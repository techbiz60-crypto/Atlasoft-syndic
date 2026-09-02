<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_lot(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A1',
            'owner_name' => 'Mohamed Alami',
            'owner_phone' => '+212600000000',
        ]);

        $response->assertCreated()->assertJsonPath('data.number', 'A1');
        $this->assertDatabaseHas('lots', ['number' => 'A1', 'building_id' => $building->id]);
    }

    public function test_conseil_member_cannot_create_a_lot(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $this->actingAs($member)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A1',
            'owner_name' => 'Mohamed Alami',
        ])->assertForbidden();
    }

    public function test_lot_cannot_reference_a_lot_type_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $buildingA = Building::factory()->for($residenceA)->create();
        $lotTypeB = LotType::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->postJson('/api/lots', [
            'building_id' => $buildingA->id,
            'lot_type_id' => $lotTypeB->id,
            'number' => 'A1',
            'owner_name' => 'Mohamed Alami',
        ])->assertUnprocessable()->assertJsonValidationErrors(['lot_type_id']);
    }

    public function test_lot_cannot_reference_a_building_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $lotTypeA = LotType::factory()->for($residenceA)->create();
        $buildingB = Building::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->postJson('/api/lots', [
            'building_id' => $buildingB->id,
            'lot_type_id' => $lotTypeA->id,
            'number' => 'A1',
            'owner_name' => 'Mohamed Alami',
        ])->assertUnprocessable()->assertJsonValidationErrors(['building_id']);
    }

    public function test_lot_number_must_be_unique_within_the_same_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)->create(['number' => 'A1']);

        $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A1',
            'owner_name' => 'Autre Personne',
        ])->assertUnprocessable()->assertJsonValidationErrors(['number']);
    }

    public function test_two_buildings_in_the_same_residence_can_reuse_the_same_lot_number(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $buildingA = Building::factory()->for($residence)->create(['name' => 'Sahil Ouad 5']);
        $buildingB = Building::factory()->for($residence)->create(['name' => 'Sahil Ouad 6']);
        $lotType = LotType::factory()->for($residence)->create();
        Lot::factory()->for($residence)->for($buildingA)->for($lotType)->create(['number' => 'A1']);

        $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $buildingB->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A1',
            'owner_name' => 'Autre Personne',
        ])->assertCreated();
    }

    public function test_admin_only_sees_lots_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        Lot::factory()->for($residenceA)->for(Building::factory()->for($residenceA))->for(LotType::factory()->for($residenceA))->create(['number' => 'A1']);
        Lot::factory()->for($residenceB)->for(Building::factory()->for($residenceB))->for(LotType::factory()->for($residenceB))->create(['number' => 'B1']);

        $adminA = User::factory()->for($residenceA)->create();

        $this->actingAs($adminA)->getJson('/api/lots')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 'A1');
    }

    public function test_creating_a_lot_beyond_the_plans_ceiling_is_blocked(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->free()->create(); // Free plan: max 6 lots
        Lot::factory()->for($residence)->for($building)->for($lotType)
            ->sequence(fn ($sequence) => ['number' => 'F'.$sequence->index])
            ->count(6)->create();

        $response = $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A7',
            'owner_name' => 'Septième Locataire',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('lots', ['number' => 'A7']);
    }

    public function test_creating_a_lot_within_the_plans_ceiling_succeeds(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->free()->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)
            ->sequence(fn ($sequence) => ['number' => 'G'.$sequence->index])
            ->count(5)->create();

        $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A6',
            'owner_name' => 'Sixième Locataire',
        ])->assertCreated();
    }

    public function test_the_custom_plan_has_no_lot_ceiling(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->active()->create(['plan' => SubscriptionPlan::Custom]);
        Lot::factory()->for($residence)->for($building)->for($lotType)
            ->sequence(fn ($sequence) => ['number' => 'C'.$sequence->index])
            ->count(150)->create();

        $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A151',
            'owner_name' => 'Locataire 151',
        ])->assertCreated();
    }

    public function test_a_residence_with_no_subscription_row_has_no_lot_ceiling(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)
            ->sequence(fn ($sequence) => ['number' => 'N'.$sequence->index])
            ->count(20)->create();

        $this->actingAs($admin)->postJson('/api/lots', [
            'building_id' => $building->id,
            'lot_type_id' => $lotType->id,
            'number' => 'A21',
            'owner_name' => 'Locataire 21',
        ])->assertCreated();
    }

    public function test_admin_cannot_update_a_lot_belonging_to_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $lotB = Lot::factory()->for($residenceB)->for(Building::factory()->for($residenceB))->for(LotType::factory()->for($residenceB))->create();

        $this->actingAs($adminA)
            ->putJson("/api/lots/{$lotB->id}", ['owner_name' => 'Hack'])
            ->assertNotFound();
    }

    public function test_admin_can_bulk_create_lots_for_a_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/lots/bulk', [
            'building_id' => $building->id,
            'lots' => [
                ['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner One', 'owner_phone' => '+212600000001'],
                ['number' => 'A2', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner Two', 'floor' => '1'],
                ['number' => 'A3', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner Three'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('created', 3);
        $this->assertSame(3, Lot::where('building_id', $building->id)->count());
        $this->assertDatabaseHas('lots', ['number' => 'A2', 'floor' => '1', 'owner_name' => 'Owner Two']);
    }

    public function test_bulk_create_rejects_a_duplicate_number_within_the_batch(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/lots/bulk', [
            'building_id' => $building->id,
            'lots' => [
                ['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner One'],
                ['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner Two'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Lot::where('building_id', $building->id)->count());
    }

    public function test_bulk_create_rejects_a_number_that_already_exists_in_the_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)->create(['number' => 'A1']);

        $response = $this->actingAs($admin)->postJson('/api/lots/bulk', [
            'building_id' => $building->id,
            'lots' => [
                ['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner One'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, Lot::where('building_id', $building->id)->count());
    }

    public function test_bulk_create_is_blocked_when_it_would_exceed_the_plans_ceiling(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->free()->create(); // Free plan: max 6 lots
        Lot::factory()->for($residence)->for($building)->for($lotType)
            ->sequence(fn ($sequence) => ['number' => 'F'.$sequence->index])
            ->count(5)->create();

        $response = $this->actingAs($admin)->postJson('/api/lots/bulk', [
            'building_id' => $building->id,
            'lots' => [
                ['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner One'],
                ['number' => 'A2', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner Two'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(5, Lot::where('building_id', $building->id)->count());
    }

    public function test_conseil_member_cannot_bulk_create_lots(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $this->actingAs($member)->postJson('/api/lots/bulk', [
            'building_id' => $building->id,
            'lots' => [['number' => 'A1', 'lot_type_id' => $lotType->id, 'owner_name' => 'Owner One']],
        ])->assertForbidden();
    }
}
