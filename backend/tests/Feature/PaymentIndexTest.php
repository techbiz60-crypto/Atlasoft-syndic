<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, Building $building): Lot
    {
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_index_lists_payments_ordered_by_id_descending_with_lot_context(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lot = $this->createLot($residence, $building);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2026, 1, 1)]);
        $first = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 100,
            'paid_at' => '2026-01-05',
            'method' => PaymentMethod::Especes,
        ]);
        $second = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 100,
            'paid_at' => '2026-01-20',
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/payments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.0.lot.building.id', $building->id);
    }

    public function test_payments_created_together_in_a_bulk_action_are_grouped_into_one_row(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lot = $this->createLot($residence, $building);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3],
            'year' => 2026,
            'paid_at' => '2026-03-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/payments');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 600)
            ->assertJsonCount(3, 'data.0.periods');
        $this->assertNotNull($response->json('data.0.batch_id'));
    }

    public function test_index_filters_by_year(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lot = $this->createLot($residence, $building);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2025, 12, 1)]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-12-28',
            'method' => PaymentMethod::Especes,
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-01-03',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/payments?year=2026');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_only_returns_the_admins_own_residence_payments(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $buildingA = Building::factory()->for($residenceA)->create();
        $buildingB = Building::factory()->for($residenceB)->create();
        $lotA = $this->createLot($residenceA, $buildingA);
        $lotB = $this->createLot($residenceB, $buildingB);

        $fundCallA = FundCall::factory()->for($residenceA)->for($lotA)->create(['amount' => 200]);
        $fundCallA->payments()->create([
            'residence_id' => $residenceA->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $fundCallB = FundCall::factory()->for($residenceB)->for($lotB)->create(['amount' => 200]);
        $fundCallB->payments()->create([
            'residence_id' => $residenceB->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($adminA)->getJson('/api/payments');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
