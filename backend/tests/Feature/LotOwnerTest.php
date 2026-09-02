<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, string $ownerName = 'Mohamed Alami'): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create(['owner_name' => $ownerName]);
    }

    public function test_creating_a_lot_seeds_its_first_owner_history_entry(): void
    {
        $residence = Residence::factory()->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $this->assertSame(1, $lot->owners()->count());
        $this->assertSame('Mohamed Alami', $lot->owners()->first()->owner_name);
    }

    public function test_admin_can_record_a_new_owner_for_a_lot(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/owners", [
            'owner_name' => 'Fatima Idrissi',
            'owner_phone' => '+212611111111',
            'started_at' => now()->addDay()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.owner_name', 'Fatima Idrissi');

        $this->assertSame('Fatima Idrissi', $lot->fresh()->owner_name);
        $this->assertSame('+212611111111', $lot->fresh()->owner_phone);
        $this->assertSame(2, $lot->owners()->count());
    }

    public function test_owner_history_lists_all_owners_newest_first(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/owners", [
            'owner_name' => 'Fatima Idrissi',
            'started_at' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson("/api/lots/{$lot->id}/owners");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.owner_name', 'Fatima Idrissi')
            ->assertJsonPath('data.1.owner_name', 'Mohamed Alami');
    }

    public function test_conseil_member_cannot_record_a_new_owner(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence);

        $this->actingAs($member)->postJson("/api/lots/{$lot->id}/owners", [
            'owner_name' => 'Fatima Idrissi',
            'started_at' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_a_new_owners_start_date_cannot_be_before_the_current_owners(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/owners", [
            'owner_name' => 'Fatima Idrissi',
            'started_at' => now()->subYears(2)->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(1, $lot->owners()->count());
        $this->assertSame('Mohamed Alami', $lot->fresh()->owner_name);
    }

    public function test_a_payment_freezes_the_owner_name_at_the_time_it_was_made(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 'Mohamed Alami');

        $fundCall = $lot->fundCalls()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'period' => now()->startOfMonth(),
        ]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $this->assertSame('Mohamed Alami', $payment->owner_name);

        // The lot is sold to a new owner afterwards.
        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/owners", [
            'owner_name' => 'Fatima Idrissi',
            'started_at' => now()->toDateString(),
        ])->assertCreated();

        // The old payment must still show the original owner, not the new one.
        $this->assertSame('Mohamed Alami', $payment->fresh()->owner_name);
        $this->assertSame('Fatima Idrissi', $lot->fresh()->owner_name);

        $response = $this->actingAs($admin)->getJson('/api/payments');
        $response->assertOk()->assertJsonPath('data.0.lot.owner_name', 'Mohamed Alami');
    }
}
