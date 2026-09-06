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
use Tests\TestCase;

class AgReportTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, int $monthlyAmount = 200): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($monthlyAmount)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_a_payment_made_the_previous_year_counts_for_the_year_it_covers(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        // Paid in December 2025, covering January 2026 — the 2026 AG has to
        // see it, even though the cash moved during 2025.
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-01-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-12-20',
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026');

        $response->assertOk()
            ->assertJsonPath('cotisations.0', 200)
            ->assertJsonPath('total_income', 200);
    }

    public function test_a_payment_covering_the_next_year_is_left_to_that_year(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2027-02-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-11-10',
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')
            ->assertOk()
            ->assertJsonPath('cotisations', array_fill(0, 12, 0))
            ->assertJsonPath('total_income', 0);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2027')
            ->assertOk()
            ->assertJsonPath('cotisations.1', 200);
    }

    public function test_opening_balance_repayments_are_reported_on_their_own_line(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $openingBalance = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 900,
            'period' => '2024-01-01',
            'is_opening_balance' => true,
        ]);
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 900,
            'paid_at' => '2026-05-10',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026');

        // Old debt settled during the year: kept apart from the year's own
        // cotisations, but still part of what came in.
        $response->assertOk()
            ->assertJsonPath('cotisations', array_fill(0, 12, 0))
            ->assertJsonPath('opening_balance_recovered.4', 900)
            ->assertJsonPath('total_income', 900);
    }

    public function test_report_aggregates_revenues_expenses_and_the_result(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-03-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-03-10',
            'method' => PaymentMethod::Virement,
        ]);

        $revenueCategory = RevenueCategory::factory()->for($residence)->create(['name' => 'Vente puces']);
        Revenue::factory()->for($residence)->create([
            'revenue_category_id' => $revenueCategory->id,
            'received_at' => '2026-03-15',
            'amount' => 150,
        ]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create(['name' => 'Entretien']);
        Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'paid_at' => '2026-04-20',
            'amount' => 100,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026');

        $response->assertOk()
            ->assertJsonPath('income_by_month.2', 350)
            ->assertJsonPath('expenses_by_month.3', 100)
            ->assertJsonPath('net_by_month.3', -100)
            ->assertJsonPath('total_income', 350)
            ->assertJsonPath('total_expenses', 100)
            ->assertJsonPath('result', 250);
    }

    public function test_the_opening_balance_is_the_previous_years_closing_balance(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2025-09-01']);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-09-10',
            'method' => PaymentMethod::Virement,
        ]);

        $previous = $this->actingAs($admin)->getJson('/api/reports/ag?year=2025')->assertOk();
        $current = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')->assertOk();

        $this->assertSame($previous->json('cash_closing_balance'), $current->json('opening_balance'));
        $current->assertJsonPath('opening_balance', 1200);
    }

    public function test_dues_cashed_in_another_year_are_reported_as_a_timing_difference(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        // 2026 dues, but the money came in during 2025.
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-01-01']);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-12-20',
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')->assertOk();

        // The exercise earned 200, but no cash moved during 2026 — so the
        // gap between opening + result and the real closing balance is 200.
        $response
            ->assertJsonPath('opening_balance', 200)
            ->assertJsonPath('result', 200)
            ->assertJsonPath('cash_closing_balance', 200)
            ->assertJsonPath('timing_difference', 200);

        $this->assertSame(
            $response->json('opening_balance') + $response->json('result') - $response->json('timing_difference'),
            $response->json('cash_closing_balance'),
        );
    }

    public function test_report_only_includes_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        $categoryB = ExpenseCategory::factory()->for($residenceB)->create();
        Expense::factory()->for($residenceB)->create([
            'expense_category_id' => $categoryB->id,
            'paid_at' => '2026-03-20',
            'amount' => 5000,
        ]);

        $this->actingAs($adminA)->getJson('/api/reports/ag?year=2026')
            ->assertOk()
            ->assertJsonPath('total_expenses', 0);
    }
}
