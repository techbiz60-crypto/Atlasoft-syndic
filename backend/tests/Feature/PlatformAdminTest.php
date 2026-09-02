<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_residence_admin_cannot_access_platform_routes(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->getJson('/api/platform/residences')->assertForbidden();
    }

    public function test_a_platform_admin_can_list_every_residence_with_its_subscription(): void
    {
        $residenceA = Residence::factory()->create(['name' => 'Résidence A']);
        $residenceB = Residence::factory()->create(['name' => 'Résidence B']);
        User::factory()->for($residenceA)->create(['name' => 'Admin A', 'email' => 'a@example.com']);
        User::factory()->for($residenceB)->create(['name' => 'Admin B', 'email' => 'b@example.com']);
        Subscription::factory()->for($residenceA)->trial()->create();
        Subscription::factory()->for($residenceB)->active()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->getJson('/api/platform/residences');

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertContains('a@example.com', $response->json('data.*.admin_email'));
        $this->assertContains('b@example.com', $response->json('data.*.admin_email'));
    }

    public function test_a_platform_admin_can_activate_a_residences_subscription(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Starter]);
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->postJson("/api/platform/residences/{$residence->id}/activate", [
            'cycle' => 'monthly',
        ]);

        $response->assertCreated();
        $this->assertSame('active', $residence->subscription->fresh()->status);

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('residence_id', $residence->id)->first();
        $this->assertSame(50, $invoice->amount);
    }

    public function test_a_platform_admin_can_override_the_plan_and_amount_when_activating(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Custom]);
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->postJson("/api/platform/residences/{$residence->id}/activate", [
            'cycle' => 'annual',
            'plan' => 'premium',
            'amount' => 3000,
        ]);

        $response->assertCreated();
        $this->assertSame(SubscriptionPlan::Premium, $residence->subscription->fresh()->plan);

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('residence_id', $residence->id)->first();
        $this->assertSame(3000, $invoice->amount);
    }

    public function test_activating_a_custom_plan_without_an_amount_fails(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Custom]);
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->postJson("/api/platform/residences/{$residence->id}/activate", ['cycle' => 'monthly'])
            ->assertStatus(422);
    }

    public function test_a_platform_admin_can_deactivate_a_residences_subscription(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->active()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->postJson("/api/platform/residences/{$residence->id}/deactivate");

        $response->assertOk();
        $this->assertSame('expired', $residence->subscription->fresh()->status);
    }

    public function test_the_free_plan_cannot_be_deactivated(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->free()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->postJson("/api/platform/residences/{$residence->id}/deactivate")
            ->assertStatus(422);
    }

    public function test_a_platform_admin_is_blocked_from_the_regular_tenant_app_routes(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)->getJson('/api/buildings')->assertForbidden();
    }
}
