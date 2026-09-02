<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Residence;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_an_expense(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = ExpenseCategory::factory()->for($residence)->create(['name' => 'Entretien']);

        $response = $this->actingAs($admin)->postJson('/api/expenses', [
            'expense_category_id' => $category->id,
            'method' => PaymentMethod::Especes->value,
            'paid_at' => '2026-08-29',
            'label' => 'Frais entretien ascenseur',
            'amount' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category.name', 'Entretien')
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.has_receipt', false);

        $this->assertDatabaseHas('expenses', ['expense_category_id' => $category->id, 'residence_id' => $residence->id]);
    }

    public function test_admin_can_attach_a_receipt_file(): void
    {
        Storage::fake();

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = ExpenseCategory::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->post('/api/expenses', [
            'expense_category_id' => $category->id,
            'method' => PaymentMethod::Virement->value,
            'paid_at' => '2026-08-29',
            'amount' => 300,
            'receipt' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('data.has_receipt', true);

        $expense = Expense::first();
        Storage::assertExists($expense->receipt_path);
    }

    public function test_expense_cannot_reference_a_category_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $categoryB = ExpenseCategory::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->postJson('/api/expenses', [
            'expense_category_id' => $categoryB->id,
            'method' => PaymentMethod::Especes->value,
            'paid_at' => '2026-08-29',
            'amount' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors(['expense_category_id']);
    }

    public function test_conseil_member_cannot_record_an_expense(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $category = ExpenseCategory::factory()->for($residence)->create();

        $this->actingAs($member)->postJson('/api/expenses', [
            'expense_category_id' => $category->id,
            'method' => PaymentMethod::Especes->value,
            'paid_at' => '2026-08-29',
            'amount' => 500,
        ])->assertForbidden();
    }

    public function test_expenses_can_be_filtered_by_year_and_month(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        Expense::factory()->for($residence)->create(['paid_at' => '2026-08-15', 'amount' => 100]);
        Expense::factory()->for($residence)->create(['paid_at' => '2026-07-15', 'amount' => 200]);

        $response = $this->actingAs($admin)->getJson('/api/expenses?year=2026&month=8');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 100);
    }

    public function test_admin_only_sees_expenses_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        Expense::factory()->for($residenceA)->create();
        Expense::factory()->for($residenceB)->create();

        $response = $this->actingAs($adminA)->getJson('/api/expenses');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_delete_an_expense_and_its_receipt(): void
    {
        Storage::fake();

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = ExpenseCategory::factory()->for($residence)->create();

        $this->actingAs($admin)->post('/api/expenses', [
            'expense_category_id' => $category->id,
            'method' => PaymentMethod::Virement->value,
            'paid_at' => '2026-08-29',
            'amount' => 300,
            'receipt' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $expense = Expense::first();
        $path = $expense->receipt_path;

        $this->actingAs($admin)->deleteJson("/api/expenses/{$expense->id}")->assertNoContent();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        Storage::assertMissing($path);
    }

    public function test_admin_cannot_delete_an_expense_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $expenseB = Expense::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->deleteJson("/api/expenses/{$expenseB->id}")->assertNotFound();
    }
}
