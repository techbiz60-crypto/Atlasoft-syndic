<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Payment;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyndicTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_syndic_transition(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->getJson('/api/syndic-transition')->assertForbidden();
        $this->actingAs($member)->postJson('/api/syndic-transition', [])->assertForbidden();
    }

    public function test_wrong_confirmation_text_is_rejected(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Pas le bon nom',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('confirmation_text');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'wrong-password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_successful_transition_creates_a_new_admin_and_deactivates_the_outgoing_team(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();
        $tresorier = User::factory()->for($residence)->tresorier()->create();
        $conseil = User::factory()->for($residence)->conseil()->create();
        $resident = User::factory()->for($residence)->coproprietaire()->create();

        $response = $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'nouveau@example.com');
        $this->assertNotEmpty($response->json('generated_password'));

        $this->assertNotNull($admin->fresh()->deactivated_at);
        $this->assertNotNull($tresorier->fresh()->deactivated_at);
        $this->assertNotNull($conseil->fresh()->deactivated_at);
        $this->assertNull($resident->fresh()->deactivated_at);

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();
        $this->assertTrue($newAdmin->isAdmin());
        $this->assertNotNull($newAdmin->email_verified_at);
        $this->assertNull($newAdmin->deactivated_at);
    }

    public function test_a_deactivated_admin_keeps_read_access_but_loses_write_access(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $this->actingAs($admin->fresh())->getJson('/api/dashboard')->assertOk();
        $this->actingAs($admin->fresh())->getJson('/api/lots')->assertOk();

        $this->actingAs($admin->fresh())->postJson('/api/buildings', ['name' => 'Bâtiment D'])
            ->assertForbidden();
    }

    public function test_the_new_admin_can_log_in_and_use_the_account(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();

        $this->actingAs($newAdmin)->getJson('/api/lots')->assertOk();
        $this->actingAs($newAdmin)->postJson('/api/buildings', ['name' => 'Bâtiment D'])->assertCreated();
    }

    public function test_records_created_before_the_closure_are_frozen_for_everyone_including_the_new_admin(): void
    {
        $this->travelTo(now()->subDay());

        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();
        $fundCall = FundCall::factory()->for($residence)->create(['amount' => 1000]);
        $payment = Payment::factory()->for($residence)->for($fundCall)->create(['amount' => 500]);
        $category = ExpenseCategory::factory()->for($residence)->create();
        $expense = Expense::factory()->for($residence)->for($category, 'category')->create();

        $this->travelBack();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();

        $this->actingAs($newAdmin)
            ->deleteJson("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}")
            ->assertForbidden();

        $this->actingAs($newAdmin)
            ->putJson("/api/expenses/{$expense->id}", [
                'expense_category_id' => $expense->expense_category_id,
                'label' => 'Modifié',
                'amount' => 999,
                'paid_at' => now()->toDateString(),
                'method' => 'especes',
            ])
            ->assertForbidden();
    }

    public function test_a_payment_created_after_the_closure_can_still_be_edited_by_the_new_admin(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();

        $fundCall = FundCall::factory()->for($residence)->create();
        $payment = Payment::factory()->for($residence)->for($fundCall)->create();

        $this->actingAs($newAdmin)
            ->putJson("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}", [
                'amount' => $payment->amount,
                'paid_at' => now()->toDateString(),
                'method' => 'especes',
            ])
            ->assertOk();
    }

    public function test_platform_support_can_reopen_a_mandate_to_lift_the_lock(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();
        $fundCall = FundCall::factory()->for($residence)->create();
        $payment = Payment::factory()->for($residence)->for($fundCall)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->postJson("/api/platform/residences/{$residence->id}/reopen-mandate", [
                'reason' => 'Clôture déclenchée par erreur, à refaire proprement.',
            ])
            ->assertOk();

        $this->assertNull($residence->fresh()->mandate_lock_boundary);

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();

        $this->actingAs($newAdmin)
            ->deleteJson("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}")
            ->assertNoContent();
    }

    public function test_a_regular_residence_admin_cannot_reopen_a_mandate(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Al Manar']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->postJson('/api/syndic-transition', [
            'confirmation_text' => 'Résidence Al Manar',
            'password' => 'password',
            'new_admin_name' => 'Nouveau Président',
            'new_admin_email' => 'nouveau@example.com',
        ])->assertCreated();

        $newAdmin = User::where('email', 'nouveau@example.com')->firstOrFail();

        $this->actingAs($newAdmin)
            ->postJson("/api/platform/residences/{$residence->id}/reopen-mandate", [
                'reason' => 'Tentative non autorisée.',
            ])
            ->assertForbidden();
    }
}
