<?php

namespace Tests\Feature;

use App\LotReferenceType;
use App\Models\Building;
use App\Models\Lot;
use App\Models\LotReference;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_admin_can_add_an_elevator_chip_to_a_lot(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value,
            'value' => '4521',
        ]);

        $response->assertCreated()->assertJsonPath('data.value', '4521');
        $this->assertDatabaseHas('lot_references', ['lot_id' => $lot->id, 'value' => '4521', 'type' => 'elevator_chip']);
    }

    public function test_a_lot_can_have_several_chips_and_garage_numbers(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1001',
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1002',
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::GarageNumber->value, 'value' => 'G-12',
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson("/api/lots/{$lot->id}/references");

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_the_same_chip_number_cannot_be_added_twice_to_the_same_lot(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1001',
        ])->assertCreated();

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1001',
        ])->assertStatus(422);
    }

    public function test_the_same_value_can_be_reused_across_different_types(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '12',
        ])->assertCreated();

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::GarageNumber->value, 'value' => '12',
        ])->assertCreated();
    }

    public function test_admin_can_delete_a_reference(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);
        $reference = LotReference::factory()->for($residence)->for($lot)->create();

        $this->actingAs($admin)->deleteJson("/api/lot-references/{$reference->id}")->assertNoContent();
        $this->assertDatabaseMissing('lot_references', ['id' => $reference->id]);
    }

    public function test_conseil_member_cannot_add_a_reference(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence);

        $this->actingAs($member)->postJson("/api/lots/{$lot->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1001',
        ])->assertForbidden();
    }

    public function test_admin_cannot_add_a_reference_to_a_lot_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $lotB = $this->createLot($residenceB);

        $this->actingAs($adminA)->postJson("/api/lots/{$lotB->id}/references", [
            'type' => LotReferenceType::ElevatorChip->value, 'value' => '1001',
        ])->assertNotFound();
    }
}
