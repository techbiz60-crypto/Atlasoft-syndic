<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LotTypeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_new_rate_effective_in_the_future(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();

        $response = $this->actingAs($admin)->postJson("/api/lot-types/{$lotType->id}/rates", [
            'amount' => 250,
            'effective_date' => Carbon::now()->addYear()->startOfYear()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('lot_type_rates', ['lot_type_id' => $lotType->id, 'amount' => 250]);

        // The current amount today is still the old one, since the new rate is not effective yet.
        $this->assertSame(200, $lotType->fresh()->current_amount);
    }

    public function test_fund_call_generation_uses_the_rate_applicable_for_the_target_period(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();

        // AG votes a new rate effective next year.
        $nextYear = Carbon::now()->addYear()->startOfYear();
        $this->actingAs($admin)->postJson("/api/lot-types/{$lotType->id}/rates", [
            'amount' => 250,
            'effective_date' => $nextYear->toDateString(),
        ])->assertCreated();

        // Generating for the current month still uses the old rate (200).
        $this->actingAs($admin)->postJson('/api/fund-calls/generate')->assertOk();
        $this->assertDatabaseHas('fund_calls', ['lot_id' => $lot->id, 'amount' => 200]);

        // Generating for a period within next year uses the new rate (250).
        $this->actingAs($admin)->postJson('/api/fund-calls/generate', [
            'period' => $nextYear->toDateString(),
        ])->assertOk();
        $this->assertSame(250, FundCall::where('lot_id', $lot->id)->whereDate('period', $nextYear)->value('amount'));
    }

    public function test_admin_cannot_add_two_rates_with_the_same_effective_date(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $sameDate = $lotType->rates->first()->effective_date->toDateString();

        $this->actingAs($admin)->postJson("/api/lot-types/{$lotType->id}/rates", [
            'amount' => 300,
            'effective_date' => $sameDate,
        ])->assertUnprocessable()->assertJsonValidationErrors(['effective_date']);
    }

    public function test_admin_cannot_delete_the_last_remaining_rate(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $rate = $lotType->rates->first();

        $this->actingAs($admin)
            ->deleteJson("/api/lot-types/{$lotType->id}/rates/{$rate->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_delete_a_rate_when_another_one_remains(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();

        $this->actingAs($admin)->postJson("/api/lot-types/{$lotType->id}/rates", [
            'amount' => 250,
            'effective_date' => Carbon::now()->addYear()->toDateString(),
        ])->assertCreated();

        $originalRate = $lotType->rates->first();

        $this->actingAs($admin)
            ->deleteJson("/api/lot-types/{$lotType->id}/rates/{$originalRate->id}")
            ->assertNoContent();
    }

    public function test_conseil_member_cannot_add_a_rate(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lotType = LotType::factory()->for($residence)->create();

        $this->actingAs($member)->postJson("/api/lot-types/{$lotType->id}/rates", [
            'amount' => 300,
            'effective_date' => now()->addMonth()->toDateString(),
        ])->assertForbidden();
    }
}
