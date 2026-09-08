<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Payment;
use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A platform-wide sweep, distinct from the many per-controller "only
 * includes the admin's own residence" tests already scattered across the
 * suite: it builds two fully independent, richly-populated residences —
 * distinguishable by name, amount and owner on every model — and checks
 * that not one of them leaks into the other's index endpoints, and that
 * neither can resolve the other's records by ID at all (not "empty data",
 * an outright 404, same as a record that doesn't exist).
 *
 * If a future endpoint is added without scoping it to the tenant, an index
 * check here fails by finding the wrong residence's distinctive value in
 * the response; if a route is added without route-model binding going
 * through the tenant scope, the direct-ID checks fail by resolving instead
 * of 404ing.
 */
class MultiTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{residence: Residence, admin: User, lot: Lot, fundCall: FundCall, payment: Payment, expense: Expense, revenue: Revenue} */
    private function buildResidence(string $residenceName, string $ownerName, string $buildingName, int $rate): array
    {
        $residence = Residence::factory()->create(['name' => $residenceName]);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create(['name' => $buildingName]);
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($rate)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create(['owner_name' => $ownerName]);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create([
            'amount' => $rate,
            'period' => Carbon::now()->startOfMonth()->subMonth(),
        ]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => $rate,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create();
        $expense = Expense::factory()->for($residence)->create([
            'expense_category_id' => $expenseCategory->id,
            'amount' => $rate * 3,
        ]);

        $revenueCategory = RevenueCategory::factory()->for($residence)->create();
        $revenue = Revenue::factory()->for($residence)->create([
            'revenue_category_id' => $revenueCategory->id,
            'amount' => $rate * 2,
        ]);

        return compact('residence', 'admin', 'lot', 'fundCall', 'payment', 'expense', 'revenue');
    }

    public function test_index_endpoints_never_mix_data_from_two_residences(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        $endpoints = [
            '/api/lots',
            '/api/buildings',
            '/api/lot-types',
            '/api/fund-calls/unpaid',
            '/api/expenses',
            '/api/revenues',
            '/api/payments',
            '/api/dashboard',
            '/api/treasury-report',
            '/api/ledger',
            '/api/reports/ag',
            '/api/reports/ag-recap',
        ];

        foreach ($endpoints as $endpoint) {
            $seenByA = $this->actingAs($a['admin'])->getJson($endpoint)->assertOk()->getContent();
            $seenByB = $this->actingAs($b['admin'])->getJson($endpoint)->assertOk()->getContent();

            $this->assertStringNotContainsString('Bouchra Iraqi', $seenByA, "{$endpoint}: A can see B's owner");
            $this->assertStringNotContainsString('Tour Sud', $seenByA, "{$endpoint}: A can see B's building");
            $this->assertStringNotContainsString('Résidence Zenith', $seenByA, "{$endpoint}: A can see B's residence name");

            $this->assertStringNotContainsString('Nadia El Amrani', $seenByB, "{$endpoint}: B can see A's owner");
            $this->assertStringNotContainsString('Tour Nord', $seenByB, "{$endpoint}: B can see A's building");
            $this->assertStringNotContainsString('Résidence Atlas', $seenByB, "{$endpoint}: B can see A's residence name");
        }
    }

    /**
     * A resident sees every lot in their own residence (that's deliberate —
     * the resident-access feature grants visibility over the whole
     * building, not just one's own apartment) — but tenant scoping must
     * still hold: nothing from the other residence.
     */
    public function test_a_resident_is_tenant_scoped_the_same_as_any_other_role(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        $residentA = User::factory()->for($a['residence'])->coproprietaire()->create(['lot_id' => $a['lot']->id]);

        $response = $this->actingAs($residentA)->getJson('/api/lots')->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('Bouchra Iraqi', $response->getContent());
    }

    public function test_a_lot_cannot_be_resolved_by_id_from_another_residence(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        // A route accepting {lot} resolves it through the tenant scope, so
        // an id belonging to another residence must 404 — same as an id
        // that simply doesn't exist — not "found but empty" and not 403.
        $this->actingAs($a['admin'])
            ->putJson("/api/lots/{$b['lot']->id}", ['owner_name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_a_payment_receipt_cannot_be_pulled_across_residences(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        $this->actingAs($a['admin'])
            ->get("/api/fund-calls/{$b['fundCall']->id}/payments/{$b['payment']->id}/receipt")
            ->assertNotFound();
    }

    public function test_an_expense_receipt_cannot_be_pulled_across_residences(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        $this->actingAs($a['admin'])
            ->get("/api/expenses/{$b['expense']->id}/receipt")
            ->assertNotFound();
    }

    public function test_a_revenue_receipt_cannot_be_pulled_across_residences(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        $this->actingAs($a['admin'])
            ->get("/api/revenues/{$b['revenue']->id}/receipt")
            ->assertNotFound();
    }

    public function test_users_management_endpoint_only_lists_the_admins_own_residence(): void
    {
        $a = $this->buildResidence('Résidence Atlas', 'Nadia El Amrani', 'Tour Nord', 300);
        $b = $this->buildResidence('Résidence Zenith', 'Bouchra Iraqi', 'Tour Sud', 500);

        User::factory()->for($b['residence'])->conseil()->create(['name' => 'Membre Zenith']);

        $response = $this->actingAs($a['admin'])->getJson('/api/users')->assertOk();

        $this->assertStringNotContainsString('Membre Zenith', $response->getContent());
    }
}
