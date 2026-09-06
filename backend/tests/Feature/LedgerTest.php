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

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create(['number' => 'A12']);
    }

    public function test_movements_are_listed_in_date_order_with_a_running_balance(): void
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

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create(['name' => 'Entretien']);
        Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'paid_at' => '2026-02-05',
            'amount' => 300,
            'label' => 'Réparation porte',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/ledger?year=2026');

        $response->assertOk()
            ->assertJsonPath('opening_balance', 1000)
            // February expense comes first, then March's payment.
            ->assertJsonPath('data.0.date', '2026-02-05')
            ->assertJsonPath('data.0.direction', 'out')
            ->assertJsonPath('data.0.label', 'Réparation porte')
            ->assertJsonPath('data.0.balance', 700)
            ->assertJsonPath('data.1.date', '2026-03-10')
            ->assertJsonPath('data.1.direction', 'in')
            ->assertJsonPath('data.1.reference', 'A12')
            ->assertJsonPath('data.1.balance', 900)
            ->assertJsonPath('closing_balance', 900)
            ->assertJsonPath('total_in', 200)
            ->assertJsonPath('total_out', 300);
    }

    public function test_the_running_balance_starts_from_what_happened_before_the_year(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 500]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $previousYear = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2025-06-01']);
        $previousYear->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-06-10',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/ledger?year=2026');

        // 500 carried in + 200 collected during 2025, and 2025 itself is not listed.
        $response->assertOk()
            ->assertJsonPath('opening_balance', 700)
            ->assertJsonPath('closing_balance', 700)
            ->assertJsonCount(0, 'data');
    }

    public function test_the_ledger_closing_balance_matches_the_treasury_summary(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 1000]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        // Collected during a previous year: both screens have to carry it
        // forward, or they disagree on where 2026 starts.
        $earlier = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2025-09-01']);
        $earlier->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2025-09-10',
            'method' => PaymentMethod::Especes,
        ]);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => '2026-03-01']);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => '2026-03-10',
            'method' => PaymentMethod::Virement,
        ]);

        $revenueCategory = RevenueCategory::factory()->for($residence)->create();
        Revenue::factory()->for($residence)->create([
            'revenue_category_id' => $revenueCategory->id,
            'received_at' => '2026-04-15',
            'amount' => 150,
        ]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create();
        Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'paid_at' => '2026-05-20',
            'amount' => 100,
        ]);

        $ledger = $this->actingAs($admin)->getJson('/api/ledger?year=2026')->assertOk();
        $summary = $this->actingAs($admin)->getJson('/api/treasury-report?year=2026')->assertOk();

        // The whole point of the two tabs: the detail has to add up to the
        // synthesis, otherwise neither can be trusted in front of an AG.
        $this->assertSame($summary->json('closing_balance'), $ledger->json('closing_balance'));
    }

    public function test_months_paid_in_one_go_appear_as_a_single_movement(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3, 4],
            'year' => 2026,
            'paid_at' => '2026-01-03',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/ledger?year=2026');

        // Four monthly rows under the hood, but the bank saw one 800 DH
        // movement — and the balance never passed through 200/400/600.
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 800)
            ->assertJsonPath('data.0.months_covered', 4)
            ->assertJsonPath('data.0.balance', 800)
            ->assertJsonPath('closing_balance', 800);
    }

    public function test_an_opening_balance_repayment_is_flagged_as_such(): void
    {
        $residence = Residence::factory()->create(['opening_balance' => 0]);
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

        $this->actingAs($admin)->getJson('/api/ledger?year=2026')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'opening_balance')
            ->assertJsonPath('data.0.amount', 900);
    }

    public function test_ledger_only_shows_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create(['opening_balance' => 0]);
        $residenceB = Residence::factory()->create(['opening_balance' => 0]);
        $adminA = User::factory()->for($residenceA)->create();

        $categoryB = ExpenseCategory::factory()->for($residenceB)->create();
        Expense::factory()->for($residenceB)->create([
            'expense_category_id' => $categoryB->id,
            'paid_at' => '2026-03-20',
            'amount' => 5000,
        ]);

        $this->actingAs($adminA)->getJson('/api/ledger?year=2026')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('closing_balance', 0);
    }
}
