<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Permission;
use App\Models\Residence;
use App\Models\RolePermission;
use App\Models\User;
use App\PaymentMethod;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function createLot(Residence $residence, int $monthlyAmount = 200): Lot
    {
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount($monthlyAmount)->create();

        return Lot::factory()->for($residence)->for($building)->for($lotType)->create();
    }

    public function test_a_new_residence_grants_financial_permissions_to_tresorier_by_default(): void
    {
        $residence = Residence::factory()->create();

        $grantedKeys = RolePermission::where('residence_id', $residence->id)
            ->where('role', Role::Tresorier)
            ->with('permission')
            ->get()
            ->pluck('permission.key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['cotisations.modifier', 'depenses.modifier', 'recettes.modifier'], $grantedKeys);
    }

    public function test_a_new_residence_grants_nothing_to_conseil_or_coproprietaire_by_default(): void
    {
        $residence = Residence::factory()->create();

        $this->assertSame(0, RolePermission::where('residence_id', $residence->id)->where('role', Role::Conseil)->count());
        $this->assertSame(0, RolePermission::where('residence_id', $residence->id)->where('role', Role::Coproprietaire)->count());
    }

    public function test_admin_always_has_every_permission_without_any_grant_row(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->assertTrue($admin->hasPermission('cotisations.modifier'));
        $this->assertTrue($admin->hasPermission('immeubles.gerer'));
        $this->assertTrue($admin->hasPermission('an-unknown-permission-key'));
    }

    public function test_tresorier_can_record_a_payment_thanks_to_the_default_grant(): void
    {
        $residence = Residence::factory()->create();
        $tresorier = User::factory()->for($residence)->tresorier()->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);

        $this->actingAs($tresorier)
            ->postJson("/api/fund-calls/{$fundCall->id}/payments", [
                'amount' => 200,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertCreated();
    }

    public function test_tresorier_cannot_manage_buildings_by_default(): void
    {
        $residence = Residence::factory()->create();
        $tresorier = User::factory()->for($residence)->tresorier()->create();

        $this->actingAs($tresorier)
            ->postJson('/api/buildings', ['name' => 'Nouveau bâtiment'])
            ->assertForbidden();
    }

    public function test_conseil_member_cannot_record_a_payment_without_a_grant(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $lot = $this->createLot($residence, 200);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);

        $this->actingAs($member)
            ->postJson("/api/fund-calls/{$fundCall->id}/payments", [
                'amount' => 200,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Virement->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_grant_expense_permission_to_conseil(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $category = ExpenseCategory::factory()->for($residence)->create();

        $this->actingAs($admin)->putJson('/api/role-permissions', [
            'grants' => [
                'conseil' => ['depenses.modifier'],
            ],
        ])->assertOk();

        $this->actingAs($member)
            ->postJson('/api/expenses', [
                'expense_category_id' => $category->id,
                'amount' => 100,
                'paid_at' => now()->toDateString(),
                'method' => PaymentMethod::Especes->value,
            ])
            ->assertCreated();
    }

    public function test_updating_role_permissions_replaces_the_previous_grants(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->putJson('/api/role-permissions', [
            'grants' => ['tresorier' => ['depenses.modifier']],
        ])->assertOk();

        $keys = RolePermission::where('residence_id', $residence->id)
            ->where('role', Role::Tresorier)
            ->with('permission')
            ->get()
            ->pluck('permission.key');

        $this->assertSame(['depenses.modifier'], $keys->all());
    }

    public function test_non_admin_cannot_view_or_update_role_permissions(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->getJson('/api/role-permissions')->assertForbidden();
        $this->actingAs($member)->putJson('/api/role-permissions', ['grants' => []])->assertForbidden();
    }

    public function test_admin_can_view_the_permissions_matrix(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->getJson('/api/role-permissions');

        $response->assertOk()
            ->assertJsonPath('data.roles', ['tresorier', 'conseil', 'coproprietaire'])
            ->assertJsonPath('data.grants.tresorier', [
                'cotisations.modifier',
                'depenses.modifier',
                'recettes.modifier',
            ]);

        $this->assertSame(Permission::count(), count($response->json('data.permissions')));
    }
}
