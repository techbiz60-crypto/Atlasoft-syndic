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
            ->assertJsonPath('prior_debt_recovered.4', 900)
            ->assertJsonPath('total_income', 900);
    }

    /**
     * The exact scenario a syndic handover raises: December 2026 goes
     * unpaid, the new conseil takes office for 2027, and the resident only
     * settles that old month in June 2027. The 2027 AG has to see it as
     * debt recovered from a prior exercise — not as a 2027 cotisation, and
     * not invisible either, which is what happened before this line only
     * recognised the pre-platform opening balance.
     */
    public function test_a_late_ordinary_cotisation_from_a_prior_year_is_recovered_debt_not_a_new_cotisation(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-12-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2027-06-15',
            'method' => PaymentMethod::Virement,
        ]);

        // The new conseil's own exercise: this must show as recovered prior
        // debt, not as one of 2027's own cotisations.
        $this->actingAs($admin)->getJson('/api/reports/ag?year=2027')
            ->assertOk()
            ->assertJsonPath('cotisations', array_fill(0, 12, 0))
            ->assertJsonPath('prior_debt_recovered.5', 200)
            ->assertJsonPath('total_income', 200);

        // The outgoing conseil's own 2026 report no longer shows this as a
        // December 2026 cotisation: with no AG date on file, 2026's books
        // are treated as closed on January 1st 2027, and this payment
        // arrived after that — it's fully recognised on 2027's report
        // instead, so it isn't double-counted across the two exercises.
        $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')
            ->assertOk()
            ->assertJsonPath('cotisations.11', 0)
            ->assertJsonPath('prior_debt_recovered', array_fill(0, 12, 0));
    }

    /**
     * The precise mechanic the user asked to verify: an exercise's books
     * don't actually close on December 31st, they close the day its AG is
     * held. A late 2026 cotisation settled before that date still belongs
     * to 2026, even once the calendar has already turned to 2027; settled
     * after that date, it's 2027's debt recovered instead.
     */
    public function test_a_prior_year_debt_settled_before_its_own_ag_still_belongs_to_that_exercise(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $residence->generalAssemblies()->create(['exercise_year' => 2026, 'held_on' => '2027-01-31']);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-12-01',
        ]);

        // Paid January 1st 2027 — the calendar year has turned, but the
        // 2026 AG (31/01/2027) hasn't happened yet: still 2026's cotisation.
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2027-01-01',
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')
            ->assertOk()
            ->assertJsonPath('cotisations.11', 200)
            ->assertJsonPath('prior_debt_recovered', array_fill(0, 12, 0));

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2027')
            ->assertOk()
            ->assertJsonPath('cotisations', array_fill(0, 12, 0))
            ->assertJsonPath('prior_debt_recovered', array_fill(0, 12, 0))
            ->assertJsonPath('total_income', 0);
    }

    /**
     * Same debt, same AG date (31/01/2027) — but paid the day after the AG,
     * once 2026's books are closed: it's now the new conseil's recovered
     * debt, and 2026 must not show it at all.
     */
    public function test_a_prior_year_debt_settled_after_its_own_ag_becomes_recovered_debt_for_the_new_exercise(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $residence->generalAssemblies()->create(['exercise_year' => 2026, 'held_on' => '2027-01-31']);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-12-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2027-06-06',
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')
            ->assertOk()
            ->assertJsonPath('cotisations.11', 0)
            ->assertJsonPath('prior_debt_recovered', array_fill(0, 12, 0))
            ->assertJsonPath('total_income', 0);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2027')
            ->assertOk()
            ->assertJsonPath('cotisations', array_fill(0, 12, 0))
            ->assertJsonPath('prior_debt_recovered.5', 200)
            ->assertJsonPath('total_income', 200);
    }

    /**
     * The AG date also moves the balance boundary: what closes into 2026's
     * balance is everything paid before its own AG (31/01/2027), not
     * everything paid before January 1st 2027 — so the December payment
     * made on the AG date's eve is part of 2026's closing balance, and the
     * one made the day after is not.
     */
    public function test_the_ag_date_moves_the_balance_boundary_not_just_the_calendar_year(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);
        $residence->generalAssemblies()->create(['exercise_year' => 2026, 'held_on' => '2027-01-31']);

        $decemberDue = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-12-01']);
        $decemberDue->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2027-01-01',
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2027')
            ->assertOk()
            ->assertJsonPath('opening_balance', 200);
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

        $this->assertSame($previous->json('closing_balance'), $current->json('opening_balance'));
        $current->assertJsonPath('opening_balance', 1200);
    }

    public function test_dues_settled_early_do_not_inflate_the_balance_carried_into_their_year(): void
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

        // 2025 earned nothing: that money belongs to the 2026 exercise, so
        // it must not be carried into 2026 as an opening balance on top of
        // being counted as a 2026 product.
        $this->actingAs($admin)->getJson('/api/reports/ag?year=2025')->assertOk()
            ->assertJsonPath('result', 0)
            ->assertJsonPath('closing_balance', 0);

        $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')->assertOk()
            ->assertJsonPath('opening_balance', 0)
            ->assertJsonPath('result', 200)
            ->assertJsonPath('closing_balance', 200);
    }

    public function test_opening_plus_result_always_equals_the_closing_balance(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-03-01']);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-03-10',
            'method' => PaymentMethod::Virement,
        ]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create();
        Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'paid_at' => '2026-04-20',
            'amount' => 100,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/ag?year=2026')->assertOk();

        // The whole point of keeping the report on one basis: there is
        // never a gap to explain between the two balances.
        $this->assertSame(
            $response->json('opening_balance') + $response->json('result'),
            $response->json('closing_balance'),
        );
        $response->assertJsonPath('closing_balance', 1100);
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
