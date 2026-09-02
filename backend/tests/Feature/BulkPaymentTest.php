<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Payment;
use App\Models\Residence;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, int $monthlyAmount = 100): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($monthlyAmount)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_a_lump_sum_covers_the_whole_year_and_generates_missing_fund_calls(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'amount' => 1200,
            'year' => 2026,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes->value,
        ]);

        $response->assertCreated()->assertJsonPath('unallocated', 0);

        $this->assertSame(12, FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->count());
        $this->assertSame(12, FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->get()->filter(fn ($c) => $c->status === 'paid')->count());
    }

    public function test_a_partial_lump_sum_only_bills_the_months_it_actually_covers(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'amount' => 350,
            'year' => 2026,
            'paid_at' => '2026-04-15',
            'method' => PaymentMethod::Virement->value,
        ]);

        // 350 / 100 = 3 whole months covered, 50 DH left over (not enough for a 4th month).
        $response->assertCreated()->assertJsonPath('unallocated', 50)->assertJsonPath('months_settled', 3);

        $calls = FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->orderBy('period')->get();
        $this->assertCount(3, $calls);
        $this->assertSame('paid', $calls[0]->status);
        $this->assertSame('paid', $calls[1]->status);
        $this->assertSame('paid', $calls[2]->status);
    }

    public function test_lump_sum_skips_already_paid_months(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $january = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 100,
            'period' => Carbon::create(2026, 1, 1),
        ]);
        $january->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 100,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'amount' => 100,
            'year' => 2026,
            'paid_at' => '2026-02-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $february = FundCall::whereYear('period', 2026)->whereMonth('period', 2)->where('lot_id', $lot->id)->first();
        $this->assertSame('paid', $february->status);
        $this->assertSame(100, $january->fresh()->paid_amount);
    }

    public function test_overpayment_reports_unallocated_amount(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'amount' => 5000,
            'year' => 2026,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes->value,
        ]);

        $response->assertCreated()->assertJsonPath('unallocated', 3800);
    }

    public function test_selecting_specific_months_settles_exactly_those_months_in_full(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $response = $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 3, 5],
            'year' => 2026,
            'paid_at' => '2026-05-15',
            'method' => PaymentMethod::Especes->value,
        ]);

        $response->assertCreated()->assertJsonPath('unallocated', 0)->assertJsonPath('months_settled', 3);

        $calls = FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->orderBy('period')->get();
        $this->assertCount(3, $calls);
        $this->assertSame([1, 3, 5], $calls->map(fn (FundCall $call) => $call->period->month)->all());
        $this->assertTrue($calls->every(fn (FundCall $call) => $call->status === 'paid'));
    }

    public function test_selecting_months_tops_up_an_already_partially_paid_month(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $january = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 100,
            'period' => Carbon::create(2026, 1, 1),
        ]);
        $january->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 40,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1],
            'year' => 2026,
            'paid_at' => '2026-02-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated()->assertJsonPath('months_settled', 1);

        $this->assertSame('paid', $january->fresh()->status);
        $this->assertSame(100, $january->fresh()->paid_amount);
    }

    public function test_selecting_an_already_fully_paid_month_settles_nothing(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 100);

        $january = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => 100,
            'period' => Carbon::create(2026, 1, 1),
        ]);
        $january->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 100,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1],
            'year' => 2026,
            'paid_at' => '2026-02-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated()->assertJsonPath('months_settled', 0);

        $this->assertCount(1, $january->fresh()->payments);
    }

    public function test_batch_receipt_lists_all_months_and_the_total_amount(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3, 4, 5, 6],
            'year' => 2026,
            'paid_at' => '2026-06-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $batchId = FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->first()->payments->first()->batch_id;

        $response = $this->actingAs($admin)->get("/api/payment-batches/{$batchId}/receipt");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_deleting_a_batch_removes_all_its_payments(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3],
            'year' => 2026,
            'paid_at' => '2026-03-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $calls = FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->get();
        $batchId = $calls->first()->payments->first()->batch_id;

        $this->actingAs($admin)->deleteJson("/api/payment-batches/{$batchId}")->assertNoContent();

        $this->assertSame(0, Payment::where('batch_id', $batchId)->count());
        $this->assertTrue($calls->fresh()->every(fn (FundCall $call) => $call->status === 'unpaid'));
    }

    public function test_deleting_an_unknown_batch_returns_not_found(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->deleteJson('/api/payment-batches/'.Str::uuid())->assertNotFound();
    }

    public function test_updating_a_batch_applies_the_new_date_method_and_note_to_every_payment_in_it(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3],
            'year' => 2026,
            'paid_at' => '2026-03-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $batchId = Payment::where('residence_id', $residence->id)->first()->batch_id;

        $response = $this->actingAs($admin)->putJson("/api/payment-batches/{$batchId}", [
            'months' => [1, 2, 3],
            'year' => 2026,
            'paid_at' => '2026-03-20',
            'method' => PaymentMethod::Virement->value,
            'notes' => 'Date corrigée',
        ]);

        $response->assertOk();

        $payments = Payment::where('batch_id', $batchId)->get();
        $this->assertCount(3, $payments);
        $this->assertTrue($payments->every(fn (Payment $payment) => $payment->paid_at->toDateString() === '2026-03-20'));
        $this->assertTrue($payments->every(fn (Payment $payment) => $payment->method === PaymentMethod::Virement));
        $this->assertTrue($payments->every(fn (Payment $payment) => $payment->notes === 'Date corrigée'));
    }

    public function test_updating_a_batch_can_change_which_months_it_covers(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lot = $this->createLot($residence, 200);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1, 2, 3],
            'year' => 2026,
            'paid_at' => '2026-03-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $batchId = Payment::where('residence_id', $residence->id)->first()->batch_id;

        $response = $this->actingAs($admin)->putJson("/api/payment-batches/{$batchId}", [
            'months' => [4, 5],
            'year' => 2026,
            'paid_at' => '2026-05-15',
            'method' => PaymentMethod::Virement->value,
        ]);

        $response->assertOk()->assertJsonPath('months_settled', 2);

        // The new months are settled under the same batch id, in full.
        $newPayments = Payment::where('batch_id', $batchId)->get();
        $this->assertCount(2, $newPayments);
        $calls = FundCall::whereYear('period', 2026)->where('lot_id', $lot->id)->orderBy('period')->get();
        $this->assertSame([1, 2, 3, 4, 5], $calls->map(fn (FundCall $call) => $call->period->month)->all());

        // Deselected months (1, 2, 3) revert to unpaid — their payments are gone.
        $deselected = $calls->whereIn('period', [
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 2, 1),
            Carbon::create(2026, 3, 1),
        ]);
        $this->assertTrue($deselected->every(fn (FundCall $call) => $call->status === 'unpaid'));

        // The kept-and-resettled months (4, 5) are paid.
        $kept = $calls->whereIn('period', [Carbon::create(2026, 4, 1), Carbon::create(2026, 5, 1)]);
        $this->assertTrue($kept->every(fn (FundCall $call) => $call->status === 'paid'));
    }

    public function test_updating_an_unknown_batch_returns_not_found(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->putJson('/api/payment-batches/'.Str::uuid(), [
            'months' => [1],
            'year' => 2026,
            'paid_at' => '2026-03-20',
            'method' => PaymentMethod::Virement->value,
        ])->assertNotFound();
    }

    public function test_conseil_member_cannot_update_a_batch(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence, 200);

        $this->actingAs($admin)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'months' => [1],
            'year' => 2026,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertCreated();

        $batchId = Payment::where('residence_id', $residence->id)->first()->batch_id;

        $this->actingAs($member)->putJson("/api/payment-batches/{$batchId}", [
            'months' => [1],
            'year' => 2026,
            'paid_at' => '2026-01-20',
            'method' => PaymentMethod::Virement->value,
        ])->assertForbidden();
    }

    public function test_conseil_member_cannot_record_a_bulk_payment(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence);

        $this->actingAs($member)->postJson("/api/lots/{$lot->id}/payments/bulk", [
            'amount' => 1200,
            'year' => 2026,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertForbidden();
    }

    public function test_admin_cannot_record_a_bulk_payment_on_a_lot_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $lotB = $this->createLot($residenceB);

        $this->actingAs($adminA)->postJson("/api/lots/{$lotB->id}/payments/bulk", [
            'amount' => 1200,
            'year' => 2026,
            'paid_at' => '2026-01-15',
            'method' => PaymentMethod::Especes->value,
        ])->assertNotFound();
    }
}
