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

class AgRecapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The factory dates its rate one year back from today, which lands
     * mid-year for a report on a past exercise — every month before that
     * date would then owe nothing. Pinning it early keeps the twelve months
     * billable whichever year the test asks for.
     */
    private function lotTypeBilling(Residence $residence, int $amount = 200): LotType
    {
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($amount)->create();
        $lotType->rates()->update(['effective_date' => '2020-01-01']);

        return $lotType;
    }

    private function payMonths(Residence $residence, Lot $lot, int $year, int $monthCount, int $amount = 200): void
    {
        foreach (range(1, $monthCount) as $month) {
            $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
                'amount' => $amount,
                'period' => Carbon::create($year, $month, 1),
            ]);
            $fundCall->payments()->create([
                'residence_id' => $residence->id,
                'amount' => $amount,
                'paid_at' => Carbon::create($year, $month, 5),
                'method' => PaymentMethod::Especes,
            ]);
        }
    }

    public function test_recap_reports_dues_collection_and_rate_per_building(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $building = Building::factory()->for($residence)->create(['name' => 'BLOC5']);

        $paidUp = Lot::factory()->for($residence)->for($building)->for($lotType)->create();
        $partial = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $this->payMonths($residence, $paidUp, 2025, 12);   // 2 400 DH
        $this->payMonths($residence, $partial, 2025, 3);   //   600 DH

        $response = $this->actingAs($admin)->getJson('/api/reports/ag-recap?year=2025');

        $response->assertOk()
            ->assertJsonPath('buildings.0.name', 'BLOC5')
            ->assertJsonPath('buildings.0.lots_count', 2)
            // 2 lots × 200 DH × 12 months.
            ->assertJsonPath('buildings.0.dues_total', 4800)
            ->assertJsonPath('buildings.0.collected_for_year', 3000)
            ->assertJsonPath('buildings.0.collection_rate', 0.625);
    }

    public function test_paying_lots_are_counted_against_the_blocks_own_size(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $building = Building::factory()->for($residence)->create(['name' => 'BLOC5']);

        $fullYear = Lot::factory()->for($residence)->for($building)->for($lotType)->create();
        $oneMonth = Lot::factory()->for($residence)->for($building)->for($lotType)->create();
        Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $this->payMonths($residence, $fullYear, 2025, 12);
        // A single month still counts: the apartment is contributing, and
        // no threshold decides otherwise.
        $this->payMonths($residence, $oneMonth, 2025, 1);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag-recap?year=2025');

        $response->assertOk()
            ->assertJsonPath('buildings.0.lots_count', 3)
            ->assertJsonPath('buildings.0.paying_lots_count', 2)
            ->assertJsonPath('buildings.0.paying_lots_rate', 2 / 3);
    }

    public function test_a_lot_paying_only_old_arrears_is_not_counted_as_paying_the_year(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $building = Building::factory()->for($residence)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $old = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2024-05-01',
        ]);
        $old->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-03-10',
            'method' => PaymentMethod::Especes,
        ]);

        $this->actingAs($admin)->getJson('/api/reports/ag-recap?year=2025')
            ->assertOk()
            ->assertJsonPath('buildings.0.paying_lots_count', 0)
            ->assertJsonPath('buildings.0.collected_for_arrears', 200);
    }

    public function test_arrears_recovered_during_the_year_are_reported_separately(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $building = Building::factory()->for($residence)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $this->payMonths($residence, $lot, 2025, 2); // 400 DH for 2025

        // A 2024 month settled during 2025.
        $old = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2024-05-01',
        ]);
        $old->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-03-10',
            'method' => PaymentMethod::Especes,
        ]);

        // Pre-platform debt settled during 2025 counts as arrears too.
        $openingBalance = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 500,
            'period' => '2023-01-01',
            'is_opening_balance' => true,
        ]);
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 500,
            'paid_at' => '2025-04-10',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag-recap?year=2025');

        $response->assertOk()
            ->assertJsonPath('buildings.0.collected_for_year', 400)
            ->assertJsonPath('buildings.0.collected_for_arrears', 700)
            ->assertJsonPath('buildings.0.collected_total', 1100);
    }

    public function test_totals_weigh_blocks_by_size_rather_than_averaging_their_rates(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);

        $big = Building::factory()->for($residence)->create(['name' => 'A-GRAND']);
        $small = Building::factory()->for($residence)->create(['name' => 'B-PETIT']);

        // Three lots fully paid in the big block, one unpaid in the small one.
        foreach (range(1, 3) as $i) {
            $lot = Lot::factory()->for($residence)->for($big)->for($lotType)->create();
            $this->payMonths($residence, $lot, 2025, 12);
        }
        Lot::factory()->for($residence)->for($small)->for($lotType)->create();

        $response = $this->actingAs($admin)->getJson('/api/reports/ag-recap?year=2025');

        // 7 200 collected of 9 600 due = 75%. Averaging the two blocks'
        // rates (100% and 0%) would wrongly read 50%.
        $response->assertOk()
            ->assertJsonPath('total.lots_count', 4)
            ->assertJsonPath('total.dues_total', 9600)
            ->assertJsonPath('total.collected_for_year', 7200)
            ->assertJsonPath('total.collection_rate', 0.75);
    }

    public function test_recap_only_covers_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        $lotTypeB = $this->lotTypeBilling($residenceB);
        $buildingB = Building::factory()->for($residenceB)->create();
        Lot::factory()->for($residenceB)->for($buildingB)->for($lotTypeB)->create();

        $this->actingAs($adminA)->getJson('/api/reports/ag-recap?year=2025')
            ->assertOk()
            ->assertJsonCount(0, 'buildings')
            ->assertJsonPath('total.lots_count', 0);
    }
}
