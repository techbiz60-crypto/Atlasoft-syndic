<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_the_default_expense_categories(): void
    {
        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Test',
            'lots_count' => 10,
            'name' => 'Admin Test',
            'email' => 'admin-cat@example.com',
            'whatsapp_number' => '+212600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $residenceId = Residence::where('name', 'Résidence Test')->first()->id;

        foreach (['Eau', 'Électricité', 'Gardiennage', 'Entretien', 'Assurance'] as $name) {
            $this->assertDatabaseHas('expense_categories', ['residence_id' => $residenceId, 'name' => $name]);
        }
    }

    public function test_admin_can_create_a_category(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/expense-categories', ['name' => 'Internet']);

        $response->assertCreated()->assertJsonPath('data.name', 'Internet');
        $this->assertDatabaseHas('expense_categories', ['name' => 'Internet', 'residence_id' => $residence->id]);
    }

    public function test_conseil_member_cannot_create_a_category(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->postJson('/api/expense-categories', ['name' => 'Internet'])->assertForbidden();
    }

    public function test_category_name_must_be_unique_within_the_same_residence(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        ExpenseCategory::factory()->for($residence)->create(['name' => 'Internet']);

        $this->actingAs($admin)->postJson('/api/expense-categories', ['name' => 'Internet'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_only_sees_categories_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        ExpenseCategory::factory()->for($residenceA)->create(['name' => 'Internet']);
        ExpenseCategory::factory()->for($residenceB)->create(['name' => 'Divers']);

        $response = $this->actingAs($adminA)->getJson('/api/expense-categories');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Internet');
    }

    public function test_admin_cannot_delete_a_category_already_used_by_an_expense(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = ExpenseCategory::factory()->for($residence)->create();
        Expense::factory()->for($residence)->create(['expense_category_id' => $category->id]);

        $this->actingAs($admin)->deleteJson("/api/expense-categories/{$category->id}")->assertStatus(422);
    }

    public function test_admin_can_delete_an_unused_category(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = ExpenseCategory::factory()->for($residence)->create();

        $this->actingAs($admin)->deleteJson("/api/expense-categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_update_a_category_belonging_to_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $categoryB = ExpenseCategory::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->putJson("/api/expense-categories/{$categoryB->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }
}
