<?php

namespace Tests\Feature;

use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_lot_type_with_an_initial_rate(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/lot-types', [
            'name' => 'Studio',
            'amount' => 200,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Studio')
            ->assertJsonPath('data.current_amount', 200);

        $this->assertDatabaseHas('lot_types', ['name' => 'Studio', 'residence_id' => $residence->id]);
        $this->assertDatabaseHas('lot_type_rates', ['amount' => 200]);
    }

    public function test_conseil_member_cannot_create_a_lot_type(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $response = $this->actingAs($member)->postJson('/api/lot-types', [
            'name' => 'Studio',
            'amount' => 200,
        ]);

        $response->assertForbidden();
    }

    public function test_lot_type_name_must_be_unique_within_the_same_residence(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        LotType::factory()->for($residence)->create(['name' => 'Studio']);

        $response = $this->actingAs($admin)->postJson('/api/lot-types', [
            'name' => 'Studio',
            'amount' => 150,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_two_residences_can_use_the_same_lot_type_name(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        LotType::factory()->for($residenceB)->create(['name' => 'Studio']);

        $adminA = User::factory()->for($residenceA)->create();

        $response = $this->actingAs($adminA)->postJson('/api/lot-types', [
            'name' => 'Studio',
            'amount' => 150,
        ]);

        $response->assertCreated();
    }

    public function test_admin_only_sees_lot_types_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        LotType::factory()->for($residenceA)->create(['name' => 'Studio A']);
        LotType::factory()->for($residenceB)->create(['name' => 'Studio B']);

        $adminA = User::factory()->for($residenceA)->create();

        $response = $this->actingAs($adminA)->getJson('/api/lot-types');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Studio A');
    }

    public function test_admin_can_rename_and_delete_a_lot_type(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        $this->actingAs($admin)
            ->putJson("/api/lot-types/{$lotType->id}", ['name' => 'Duplex'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Duplex');

        $this->actingAs($admin)
            ->deleteJson("/api/lot-types/{$lotType->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('lot_types', ['id' => $lotType->id]);
    }

    public function test_admin_cannot_update_a_lot_type_belonging_to_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $lotTypeB = LotType::factory()->for($residenceB)->create();

        $this->actingAs($adminA)
            ->putJson("/api/lot-types/{$lotTypeB->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }
}
