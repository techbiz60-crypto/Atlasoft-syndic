<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function lotTypeBilling(Residence $residence, int $amount = 200): LotType
    {
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($amount)->create();
        $lotType->rates()->update(['effective_date' => '2020-01-01']);

        return $lotType;
    }

    public function test_cash_balance_matches_residence_cash_balance_before(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-03-01']);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-03-10',
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?from=2026-01-01&to=2026-09-15');

        $response->assertOk()->assertJsonPath('cash_balance', 1200);
    }

    public function test_a_building_filter_only_narrows_cotisation_figures(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);

        $buildingA = Building::factory()->for($residence)->create();
        $buildingB = Building::factory()->for($residence)->create();
        $lotA = Lot::factory()->for($residence)->for($buildingA)->for($lotType)->create();
        $lotB = Lot::factory()->for($residence)->for($buildingB)->for($lotType)->create();

        $callA = FundCall::factory()->for($residence)->for($lotA)->create(['amount' => 200, 'period' => '2026-03-01']);
        $callA->payments()->create(['residence_id' => $residence->id, 'amount' => 200, 'paid_at' => '2026-03-10', 'method' => PaymentMethod::Especes]);

        $callB = FundCall::factory()->for($residence)->for($lotB)->create(['amount' => 200, 'period' => '2026-03-01']);
        $callB->payments()->create(['residence_id' => $residence->id, 'amount' => 200, 'paid_at' => '2026-03-12', 'method' => PaymentMethod::Especes]);

        // Revenue/expense are residence-wide, not tied to either building.
        $expenseCategory = ExpenseCategory::factory()->for($residence)->create();
        Expense::factory()->for($residence)->create(['expense_category_id' => $expenseCategory->id, 'paid_at' => '2026-03-15', 'amount' => 50]);

        $all = $this->actingAs($admin)->getJson('/api/dashboard?from=2026-01-01&to=2026-09-15')->assertOk();
        $onlyA = $this->actingAs($admin)
            ->getJson("/api/dashboard?from=2026-01-01&to=2026-09-15&building_id={$buildingA->id}")
            ->assertOk();

        $all->assertJsonPath('collected_total', 400)->assertJsonPath('expenses_total', 50);
        $onlyA
            ->assertJsonPath('collected_total', 200)
            // Expenses have no building of their own — filtering by A must
            // not silently zero out spending that belongs to the residence.
            ->assertJsonPath('expenses_total', 50);
    }

    public function test_collection_rate_is_computed_on_the_covered_month_basis(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        // January due, paid in December of the previous year — must still
        // count for January, same rule as the AG report.
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-01-01']);
        $fundCall->payments()->create(['residence_id' => $residence->id, 'amount' => 200, 'paid_at' => '2025-12-20', 'method' => PaymentMethod::Virement]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?from=2026-01-01&to=2026-01-31');

        $response->assertOk()
            ->assertJsonPath('dues_total', 200)
            ->assertJsonPath('collected_for_range', 200)
            ->assertJsonPath('collection_rate', 1)
            // The cash itself moved in 2025, so it must not appear as cash
            // collected within a 2026 window.
            ->assertJsonPath('collected_total', 0);
    }

    public function test_top_unpaid_lists_the_largest_debts_first(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = $this->lotTypeBilling($residence);

        $smallDebt = Lot::factory()->for($residence)->for($building)->for($lotType)->create(['number' => 'A1']);
        $bigDebt = Lot::factory()->for($residence)->for($building)->for($lotType)->create(['number' => 'A2']);

        FundCall::factory()->for($residence)->for($smallDebt)->create([
            'amount' => 500, 'period' => '2024-01-01', 'is_opening_balance' => true,
        ]);
        FundCall::factory()->for($residence)->for($bigDebt)->create([
            'amount' => 5000, 'period' => '2024-01-01', 'is_opening_balance' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?from=2026-01-01&to=2026-09-15');

        $response->assertOk()
            ->assertJsonPath('top_unpaid.0.lot_number', 'A2')
            ->assertJsonPath('top_unpaid.1.lot_number', 'A1');
    }

    public function test_monthly_series_covers_every_month_in_range_with_a_running_balance(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();

        $revenueCategory = RevenueCategory::factory()->for($residence)->create();
        Revenue::factory()->for($residence)->create([
            'revenue_category_id' => $revenueCategory->id,
            'received_at' => '2026-02-10',
            'amount' => 300,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?from=2026-01-01&to=2026-03-31');

        $response->assertOk()
            ->assertJsonCount(3, 'monthly_series')
            ->assertJsonPath('monthly_series.0.month', '2026-01')
            ->assertJsonPath('monthly_series.0.balance', 1000)
            ->assertJsonPath('monthly_series.1.income', 300)
            ->assertJsonPath('monthly_series.1.balance', 1300)
            ->assertJsonPath('monthly_series.2.balance', 1300);
    }

    public function test_dashboard_only_covers_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create(['opening_balance' => 0]);
        $residenceB = Residence::factory()->create(['opening_balance' => 5000]);
        $adminA = User::factory()->for($residenceA)->create();

        $this->actingAs($adminA)->getJson('/api/dashboard?from=2026-01-01&to=2026-09-15')
            ->assertOk()
            ->assertJsonPath('cash_balance', 0)
            ->assertJsonCount(0, 'top_unpaid');
    }
}
