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

class TreasuryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_cotisations_revenues_and_expenses_by_month(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

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

        $revenueCategory = RevenueCategory::factory()->for($residence)->create(['name' => 'Vente puces ascenseur']);
        Revenue::factory()->for($residence)->create([
            'revenue_category_id' => $revenueCategory->id,
            'received_at' => '2026-03-15',
            'amount' => 150,
        ]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create(['name' => 'Entretien']);
        Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'paid_at' => '2026-03-20',
            'amount' => 100,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/treasury-report?year=2026');

        $response->assertOk()
            ->assertJsonPath('opening_balance', 1000)
            ->assertJsonPath('cotisations.2', 200)
            ->assertJsonPath('income_by_month.2', 350)
            ->assertJsonPath('expenses_by_month.2', 100)
            ->assertJsonPath('net_by_month.2', 250)
            ->assertJsonPath('balance_by_month.2', 1250)
            ->assertJsonPath('closing_balance', 1250);
    }

    public function test_a_payment_made_the_previous_year_counts_for_the_covered_year_without_touching_the_balance(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        // Paid in December 2025, but covering January 2026 — the AG for 2026
        // has to see it, the 2026 cash position must not.
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

        $response = $this->actingAs($admin)->getJson('/api/treasury-report?year=2026');

        $response->assertOk()
            ->assertJsonPath('cotisations_for_year.0', 200)
            ->assertJsonPath('cotisations.0', 0)
            ->assertJsonPath('income_by_month.0', 0)
            ->assertJsonPath('closing_balance', 1000);
    }

    public function test_a_payment_covering_next_year_stays_out_of_this_years_covered_line(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        // Cash in during 2026, but it buys a month of 2027.
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

        $response = $this->actingAs($admin)->getJson('/api/treasury-report?year=2026');

        $response->assertOk()
            ->assertJsonPath('cotisations.10', 200)
            ->assertJsonPath('cotisations_for_year', array_fill(0, 12, 0))
            ->assertJsonPath('closing_balance', 200);
    }

    public function test_the_covered_line_ignores_opening_balance_repayments(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

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

        $response = $this->actingAs($admin)->getJson('/api/treasury-report?year=2026');

        // Real cash in, but it settles old debt — not a 2026 cotisation.
        $response->assertOk()
            ->assertJsonPath('cotisations.4', 900)
            ->assertJsonPath('cotisations_for_year', array_fill(0, 12, 0));
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

        $response = $this->actingAs($adminA)->getJson('/api/treasury-report?year=2026');

        $response->assertOk()->assertJsonPath('expenses_by_month.2', 0);
    }
}
