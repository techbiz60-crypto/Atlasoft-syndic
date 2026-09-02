<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_a_revenue(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = RevenueCategory::factory()->for($residence)->create(['name' => 'Vente de puces ascenseur']);

        $response = $this->actingAs($admin)->postJson('/api/revenues', [
            'revenue_category_id' => $category->id,
            'method' => PaymentMethod::Especes->value,
            'received_at' => '2026-08-29',
            'label' => 'Vente de 5 puces à M. Alami',
            'amount' => 250,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category.name', 'Vente de puces ascenseur')
            ->assertJsonPath('data.amount', 250);

        $this->assertDatabaseHas('revenues', ['revenue_category_id' => $category->id, 'residence_id' => $residence->id]);
    }

    public function test_admin_can_attach_a_receipt_file(): void
    {
        Storage::fake();

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = RevenueCategory::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->post('/api/revenues', [
            'revenue_category_id' => $category->id,
            'method' => PaymentMethod::Virement->value,
            'received_at' => '2026-08-29',
            'amount' => 300,
            'receipt' => UploadedFile::fake()->create('recu.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('data.has_receipt', true);

        $revenue = Revenue::first();
        Storage::assertExists($revenue->receipt_path);
    }

    public function test_conseil_member_cannot_record_a_revenue(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();
        $category = RevenueCategory::factory()->for($residence)->create();

        $this->actingAs($member)->postJson('/api/revenues', [
            'revenue_category_id' => $category->id,
            'method' => PaymentMethod::Especes->value,
            'received_at' => '2026-08-29',
            'amount' => 250,
        ])->assertForbidden();
    }

    public function test_revenues_can_be_filtered_by_year_and_month(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        Revenue::factory()->for($residence)->create(['received_at' => '2026-08-15', 'amount' => 100]);
        Revenue::factory()->for($residence)->create(['received_at' => '2026-07-15', 'amount' => 200]);

        $response = $this->actingAs($admin)->getJson('/api/revenues?year=2026&month=8');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 100);
    }

    public function test_admin_only_sees_revenues_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        Revenue::factory()->for($residenceA)->create();
        Revenue::factory()->for($residenceB)->create();

        $response = $this->actingAs($adminA)->getJson('/api/revenues');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_delete_a_revenue_and_its_receipt(): void
    {
        Storage::fake();

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = RevenueCategory::factory()->for($residence)->create();

        $this->actingAs($admin)->post('/api/revenues', [
            'revenue_category_id' => $category->id,
            'method' => PaymentMethod::Virement->value,
            'received_at' => '2026-08-29',
            'amount' => 300,
            'receipt' => UploadedFile::fake()->create('recu.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $revenue = Revenue::first();
        $path = $revenue->receipt_path;

        $this->actingAs($admin)->deleteJson("/api/revenues/{$revenue->id}")->assertNoContent();

        $this->assertDatabaseMissing('revenues', ['id' => $revenue->id]);
        Storage::assertMissing($path);
    }

    public function test_admin_cannot_delete_a_revenue_from_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $revenueB = Revenue::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->deleteJson("/api/revenues/{$revenueB->id}")->assertNotFound();
    }
}
