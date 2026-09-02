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

class FundCallMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_shows_one_row_per_lot_and_twelve_months(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => Carbon::create(2026, 3, 1),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(12, 'data.0.months')
            ->assertJsonPath('data.0.months.2.status', 'unpaid')
            ->assertJsonPath('data.0.months.0.status', 'none');
    }

    public function test_matrix_projects_the_current_rate_for_months_not_yet_billed(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()
            ->assertJsonPath('data.0.months.0.status', 'none')
            ->assertJsonPath('data.0.months.0.fund_call_id', null)
            ->assertJsonPath('data.0.months.0.amount', 200);
    }

    public function test_matrix_reflects_paid_status_after_a_full_payment(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => Carbon::create(2026, 5, 1),
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()->assertJsonPath('data.0.months.4.status', 'paid');
    }

    public function test_matrix_can_be_filtered_by_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $buildingA = Building::factory()->for($residence)->create();
        $buildingB = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        Lot::factory()->for($residence)->for($buildingA)->for($lotType)->create(['number' => 'A1']);
        Lot::factory()->for($residence)->for($buildingB)->for($lotType)->create(['number' => 'B1']);

        $response = $this->actingAs($admin)->getJson("/api/fund-calls/matrix?building_id={$buildingA->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lot_number', 'A1');
    }

    public function test_matrix_only_shows_lots_from_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $buildingA = Building::factory()->for($residenceA)->create();
        $buildingB = Building::factory()->for($residenceB)->create();
        $lotTypeA = LotType::factory()->for($residenceA)->withMonthlyAmount(200)->create();
        $lotTypeB = LotType::factory()->for($residenceB)->withMonthlyAmount(200)->create();
        Lot::factory()->for($residenceA)->for($buildingA)->for($lotTypeA)->create();
        Lot::factory()->for($residenceB)->for($buildingB)->for($lotTypeB)->create();

        $response = $this->actingAs($adminA)->getJson('/api/fund-calls/matrix');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
