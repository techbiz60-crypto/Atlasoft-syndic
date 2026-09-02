<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_the_default_revenue_categories(): void
    {
        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Recettes',
            'lots_count' => 10,
            'name' => 'Admin Test',
            'email' => 'admin-rev@example.com',
            'whatsapp_number' => '+212600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $residenceId = Residence::where('name', 'Résidence Recettes')->first()->id;

        foreach (['Vente de biens/services', 'Location', 'Pénalités de retard', 'Divers'] as $name) {
            $this->assertDatabaseHas('revenue_categories', ['residence_id' => $residenceId, 'name' => $name]);
        }
    }

    public function test_admin_can_create_a_category(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->postJson('/api/revenue-categories', ['name' => 'Vente puces ascenseur']);

        $response->assertCreated()->assertJsonPath('data.name', 'Vente puces ascenseur');
        $this->assertDatabaseHas('revenue_categories', ['name' => 'Vente puces ascenseur', 'residence_id' => $residence->id]);
    }

    public function test_conseil_member_cannot_create_a_category(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->postJson('/api/revenue-categories', ['name' => 'Divers'])->assertForbidden();
    }

    public function test_category_name_must_be_unique_within_the_same_residence(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        RevenueCategory::factory()->for($residence)->create(['name' => 'Location']);

        $this->actingAs($admin)->postJson('/api/revenue-categories', ['name' => 'Location'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_only_sees_categories_from_their_own_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();

        RevenueCategory::factory()->for($residenceA)->create(['name' => 'Location']);
        RevenueCategory::factory()->for($residenceB)->create(['name' => 'Divers']);

        $response = $this->actingAs($adminA)->getJson('/api/revenue-categories');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Location');
    }

    public function test_admin_cannot_delete_a_category_already_used_by_a_revenue(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = RevenueCategory::factory()->for($residence)->create();
        Revenue::factory()->for($residence)->create(['revenue_category_id' => $category->id]);

        $this->actingAs($admin)->deleteJson("/api/revenue-categories/{$category->id}")->assertStatus(422);
    }

    public function test_admin_can_delete_an_unused_category(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $category = RevenueCategory::factory()->for($residence)->create();

        $this->actingAs($admin)->deleteJson("/api/revenue-categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('revenue_categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_update_a_category_belonging_to_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $categoryB = RevenueCategory::factory()->for($residenceB)->create();

        $this->actingAs($adminA)->putJson("/api/revenue-categories/{$categoryB->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }
}
