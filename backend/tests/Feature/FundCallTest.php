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

class FundCallTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, int $monthlyAmount = 200): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($monthlyAmount)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_admin_can_generate_fund_calls_for_the_current_month(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotA = $this->createLot($residence, 200);
        $lotB = $this->createLot($residence, 350);

        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();

        $this->assertDatabaseHas('fund_calls', ['lot_id' => $lotA->id, 'amount' => 200]);
        $this->assertDatabaseHas('fund_calls', ['lot_id' => $lotB->id, 'amount' => 350]);
    }

    public function test_generating_twice_for_the_same_month_does_not_duplicate(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence);

        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();
        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();

        $this->assertSame(1, FundCall::where('lot_id', $lot->id)->count());
    }

    public function test_generating_fund_calls_only_affects_the_admins_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $lotA = $this->createLot($residenceA);
        $lotB = $this->createLot($residenceB);

        $this->actingAs($adminA)->postJson('/api/fund-calls/generate')->assertOk();

        $this->assertDatabaseHas('fund_calls', ['lot_id' => $lotA->id]);
        $this->assertDatabaseMissing('fund_calls', ['lot_id' => $lotB->id]);
    }

    public function test_recording_a_full_payment_marks_the_fund_call_as_paid(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();
        $fundCall = $lot->fundCalls()->first();

        $response = $this->actingAs($admin)->postJson("/api/fund-calls/{$fundCall->id}/payments", [
            'amount' => 200,
            'paid_at' => now()->toDateString(),
            'method' => PaymentMethod::Virement->value,
        ]);

        $response->assertCreated()->assertJsonPath('fund_call.status', 'paid');
    }

    public function test_recording_a_partial_payment_marks_the_fund_call_as_partial(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();
        $fundCall = $lot->fundCalls()->first();

        $response = $this->actingAs($admin)->postJson("/api/fund-calls/{$fundCall->id}/payments", [
            'amount' => 80,
            'paid_at' => now()->toDateString(),
            'method' => PaymentMethod::Especes->value,
        ]);

        $response->assertCreated()->assertJsonPath('fund_call.status', 'partial');
        $this->assertSame(120, $fundCall->fresh()->amount - $fundCall->fresh()->paid_amount);
    }

    public function test_admin_can_edit_an_existing_payment(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 80,
            'paid_at' => '2026-01-10',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}", [
            'amount' => 200,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Virement->value,
            'notes' => 'Correction du montant',
        ]);

        $response->assertOk()->assertJsonPath('fund_call.status', 'paid');
        $this->assertSame(200, $payment->fresh()->amount);
        $this->assertSame('Correction du montant', $payment->fresh()->notes);
    }

    public function test_editing_a_payment_from_another_fund_call_returns_not_found(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $fundCallA = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2026, 1, 1)]);
        $fundCallB = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2026, 2, 1)]);
        $payment = $fundCallB->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 100,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/fund-calls/{$fundCallA->id}/payments/{$payment->id}", [
                'amount' => 150,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertNotFound();
    }

    public function test_conseil_member_cannot_edit_a_payment(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 80,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $this->actingAs($member)
            ->putJson("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}", [
                'amount' => 200,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_download_a_payment_receipt_as_pdf(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->get("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}/receipt");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipt_for_a_payment_from_another_fund_call_returns_not_found(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $fundCallA = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2026, 1, 1)]);
        $fundCallB = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200, 'period' => Carbon::create(2026, 2, 1)]);
        $payment = $fundCallB->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $this->actingAs($admin)
            ->get("/api/fund-calls/{$fundCallA->id}/payments/{$payment->id}/receipt")
            ->assertNotFound();
    }

    public function test_show_returns_a_fund_call_with_its_payments(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 80,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/fund-calls/{$fundCall->id}");

        $response->assertOk()->assertJsonCount(1, 'data.payments');
    }

    public function test_conseil_member_cannot_generate_fund_calls_or_record_payments(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence);

        $this->actingAs($member)->postJson('/api/fund-calls/generate')->assertForbidden();

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create();

        $this->actingAs($member)
            ->postJson("/api/fund-calls/{$fundCall->id}/payments", [
                'amount' => 100,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_record_a_payment_on_a_fund_call_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $lotB = $this->createLot($residenceB);
        $fundCallB = FundCall::factory()->for($residenceB)->for($lotB)->create();

        $this->actingAs($adminA)
            ->postJson("/api/fund-calls/{$fundCallB->id}/payments", [
                'amount' => 100,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertNotFound();
    }

    public function test_unpaid_endpoint_counts_every_month_of_the_year_up_to_the_current_one(): void
    {
        // Frozen mid-September: January through September = 9 months owed.
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-07-01',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lot_id', $lot->id)
            ->assertJsonPath('data.0.total_due', 1800)
            ->assertJsonPath('data.0.months_late', 9)
            ->assertJsonPath('data.0.oldest_unpaid_period', '2026-01-01');
    }

    public function test_unpaid_endpoint_excludes_lots_whose_year_is_fully_paid(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        foreach (range(1, 9) as $month) {
            $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
                'amount' => 200,
                'period' => Carbon::create(2026, $month, 1),
            ]);
            $fundCall->payments()->create([
                'residence_id' => $residence->id,
                'amount' => 200,
                'paid_at' => Carbon::create(2026, $month, 5),
                'method' => PaymentMethod::Virement,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unpaid_endpoint_only_counts_the_remainder_of_a_partially_paid_month(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-03-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 150,
            'paid_at' => '2026-03-10',
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        // 8 fully unpaid months (1600) + the 50 DH still owed on March.
        $response->assertOk()->assertJsonPath('data.0.total_due', 1650);
    }

    public function test_admin_can_record_an_opening_balance_for_a_lot(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $response = $this->actingAs($admin)->postJson('/api/fund-calls', [
            'lot_id' => $lot->id,
            'amount' => 7200,
            'period' => '2020-01-01',
            'is_opening_balance' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.is_opening_balance', true);
        $this->assertDatabaseHas('fund_calls', ['lot_id' => $lot->id, 'amount' => 7200, 'is_opening_balance' => 1]);
    }

    public function test_a_lot_cannot_have_two_opening_balances(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 7200,
            'period' => '2020-01-01',
            'is_opening_balance' => true,
        ]);

        $this->actingAs($admin)->postJson('/api/fund-calls', [
            'lot_id' => $lot->id,
            'amount' => 500,
            'period' => '2021-01-01',
            'is_opening_balance' => true,
        ])->assertStatus(422);
    }

    public function test_opening_balance_is_excluded_from_the_cotisations_matrix(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 7200,
            'period' => '2026-01-01',
            'is_opening_balance' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()->assertJsonPath('data.0.months.0.fund_call_id', null);
    }

    public function test_matrix_endpoint_exposes_a_lots_opening_balance(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $openingBalance = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 7200,
            'period' => '2020-01-01',
            'is_opening_balance' => true,
        ]);
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 1200,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()
            ->assertJsonPath('data.0.opening_balance.amount', 7200)
            ->assertJsonPath('data.0.opening_balance.paid_amount', 1200)
            ->assertJsonPath('data.0.opening_balance.status', 'partial');
    }

    public function test_matrix_endpoint_exposes_paid_amount_per_month(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 200,
            'period' => '2026-03-01',
        ]);
        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 80,
            'paid_at' => now(),
            'method' => PaymentMethod::Especes,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()
            ->assertJsonPath('data.0.months.2.paid_amount', 80)
            ->assertJsonPath('data.0.months.2.status', 'partial')
            ->assertJsonPath('data.0.months.0.paid_amount', 0);
    }

    public function test_matrix_endpoint_returns_null_opening_balance_when_the_lot_has_none(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $this->createLot($residence, 200);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/matrix?year=2026');

        $response->assertOk()->assertJsonPath('data.0.opening_balance', null);
    }

    public function test_unpaid_endpoint_adds_the_opening_balance_to_the_current_years_months(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 800,
            'period' => '2025-09-01',
            'is_opening_balance' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        // 800 DH of pre-2026 debt + 9 unbilled months of 2026 at 200 DH.
        $response->assertOk()
            ->assertJsonPath('data.0.total_due', 2600)
            ->assertJsonPath('data.0.opening_balance_due', 800)
            ->assertJsonPath('data.0.months_late', 13)
            ->assertJsonPath('data.0.oldest_unpaid_period', '2025-09-01');
    }

    public function test_a_settled_opening_balance_still_leaves_the_current_years_months_due(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $openingBalance = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 2400,
            'period' => '2025-01-01',
            'is_opening_balance' => true,
        ]);
        $openingBalance->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 2400,
            'paid_at' => '2026-04-05',
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        $response->assertOk()
            ->assertJsonPath('data.0.opening_balance_due', 0)
            ->assertJsonPath('data.0.total_due', 1800)
            ->assertJsonPath('data.0.months_late', 9);
    }

    public function test_an_opening_balance_dated_inside_the_year_is_not_double_counted(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 15));

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 500,
            'period' => '2026-03-01',
            'is_opening_balance' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/fund-calls/unpaid');

        // The lump sum covers everything up to March, so only April through
        // September (6 months at 200 DH) are projected on top of it.
        $response->assertOk()->assertJsonPath('data.0.total_due', 1700);
    }

    public function test_admin_can_correct_an_opening_balances_amount_and_date(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 7200,
            'period' => '2020-01-01',
            'is_opening_balance' => true,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/fund-calls/{$fundCall->id}/opening-balance", [
            'amount' => 9600,
            'period' => '2019-06-01',
        ]);

        $response->assertOk()->assertJsonPath('data.amount', 9600);
        $this->assertSame('2019-06-01', $fundCall->fresh()->period->toDateString());
    }

    public function test_a_fund_call_that_has_payments_cannot_be_deleted(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->deleteJson("/api/fund-calls/{$fundCall->id}")->assertStatus(422);

        $this->assertDatabaseHas('fund_calls', ['id' => $fundCall->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_a_fund_call_without_payments_can_be_deleted(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);

        $this->actingAs($admin)->deleteJson("/api/fund-calls/{$fundCall->id}")->assertNoContent();

        $this->assertDatabaseMissing('fund_calls', ['id' => $fundCall->id]);
    }

    public function test_opening_balance_endpoint_refuses_a_regular_fund_call(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);

        $this->actingAs($admin)
            ->putJson("/api/fund-calls/{$fundCall->id}/opening-balance", ['amount' => 500, 'period' => '2026-01-01'])
            ->assertNotFound();
    }
}
