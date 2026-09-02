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

class PaymentsReportTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, Building $building, int $monthlyAmount = 200): Lot
    {
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($monthlyAmount)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_report_lists_paid_months_and_omits_unpaid_ones(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lot = $this->createLot($residence, $building, 200);

        $january = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-01-01']);
        $january->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes,
        ]);
        FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-02-01']);

        $response = $this->actingAs($admin)->getJson("/api/reports/payments?year=2026&building_id={$building->id}");

        $response->assertOk()
            ->assertJsonPath('rows.0.months.0', 200)
            ->assertJsonPath('rows.0.months.1', null)
            ->assertJsonPath('rows.0.total', 200);
    }

    public function test_report_splits_opening_balance_between_before_and_during_the_year(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lot = $this->createLot($residence, $building, 200);

        $openingBalance = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 3600,
            'period' => '2020-01-01',
            'is_opening_balance' => true,
        ]);
        // Paid 1200 before 2026 started, then 600 more during 2026.
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 1200,
            'paid_at' => '2025-06-01',
            'method' => PaymentMethod::Especes,
        ]);
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 600,
            'paid_at' => '2026-03-01',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/reports/payments?year=2026&building_id={$building->id}");

        // Owed 3600 - 1200 paid before 2026 = 2400 still outstanding going into the year.
        $response->assertOk()
            ->assertJsonPath('rows.0.opening_balance_remaining', -2400)
            ->assertJsonPath('rows.0.opening_balance_paid_this_year', 600)
            ->assertJsonPath('rows.0.total', 600);
    }

    public function test_report_omits_opening_balance_fields_when_the_lot_has_none(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $this->createLot($residence, $building);

        $response = $this->actingAs($admin)->getJson("/api/reports/payments?year=2026&building_id={$building->id}");

        $response->assertOk()
            ->assertJsonPath('rows.0.opening_balance_remaining', null)
            ->assertJsonPath('rows.0.opening_balance_paid_this_year', null);
    }

    public function test_report_scopes_to_the_requested_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $buildingA = Building::factory()->for($residence)->create();
        $buildingB = Building::factory()->for($residence)->create();
        $this->createLot($residence, $buildingA);
        $this->createLot($residence, $buildingB);

        $response = $this->actingAs($admin)->getJson('/api/reports/payments?year='.Carbon::now()->year."&building_id={$buildingA->id}");

        $response->assertOk()->assertJsonCount(1, 'rows');
    }

    public function test_conseil_member_can_view_the_report(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $building = Building::factory()->for($residence)->create();
        $this->createLot($residence, $building);

        $this->actingAs($member)
            ->getJson('/api/reports/payments?year='.Carbon::now()->year."&building_id={$building->id}")
            ->assertOk();
    }
}
