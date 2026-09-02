<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_six_or_fewer_lots_gets_a_permanent_free_plan(): void
    {
        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Test',
            'lots_count' => 6,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'whatsapp_number' => '0600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $subscription = Subscription::withoutGlobalScopes()->where('residence_id', $response->json('user.residence_id'))->first();

        $this->assertSame(SubscriptionPlan::Free, $subscription->plan);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->is_writable);
        $this->assertSame('free', $subscription->status);
    }

    public function test_registering_with_more_than_six_lots_starts_a_fifteen_day_trial_on_the_matching_plan(): void
    {
        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Test',
            'lots_count' => 20,
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'whatsapp_number' => '0600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $subscription = Subscription::withoutGlobalScopes()->where('residence_id', $response->json('user.residence_id'))->first();

        $this->assertSame(SubscriptionPlan::Standard, $subscription->plan);
        $this->assertSame('trial', $subscription->status);
        $this->assertTrue($subscription->is_writable);
        $this->assertEqualsWithDelta(15, Carbon::now()->diffInDays($subscription->trial_ends_at, false), 1);
    }

    public function test_a_free_plan_is_always_writable(): void
    {
        $subscription = Subscription::factory()->free()->create();

        $this->assertTrue($subscription->is_writable);
    }

    public function test_a_trial_subscription_is_writable_until_it_ends(): void
    {
        $trial = Subscription::factory()->trial()->create();
        $expiredTrial = Subscription::factory()->expiredTrial()->create();

        $this->assertTrue($trial->is_writable);
        $this->assertFalse($expiredTrial->is_writable);
        $this->assertSame('trial', $trial->status);
        $this->assertSame('expired', $expiredTrial->status);
    }

    public function test_an_active_subscription_is_writable_until_its_period_ends(): void
    {
        $active = Subscription::factory()->active()->create();
        $expired = Subscription::factory()->expired()->create();

        $this->assertTrue($active->is_writable);
        $this->assertFalse($expired->is_writable);
        $this->assertSame('active', $active->status);
        $this->assertSame('expired', $expired->status);
    }

    public function test_write_routes_are_blocked_when_the_subscription_has_expired(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->expired()->create();

        $this->actingAs($admin)
            ->postJson('/api/buildings', ['name' => 'Bâtiment B'])
            ->assertForbidden();
    }

    public function test_write_routes_stay_open_during_an_active_trial(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->trial()->create();

        $this->actingAs($admin)
            ->postJson('/api/buildings', ['name' => 'Bâtiment B'])
            ->assertCreated();
    }

    public function test_write_routes_stay_open_when_the_residence_has_no_subscription_row(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)
            ->postJson('/api/buildings', ['name' => 'Bâtiment B'])
            ->assertCreated();
    }

    public function test_subscription_endpoint_returns_the_authenticated_users_own_residence_subscription(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        Subscription::factory()->for($residenceA)->trial()->create();
        Subscription::factory()->for($residenceB)->active()->create();

        $response = $this->actingAs($adminA)->getJson('/api/subscription');

        $response->assertOk()->assertJsonPath('data.residence_id', $residenceA->id)->assertJsonPath('data.status', 'trial');
    }

    public function test_activate_command_extends_the_period_and_records_an_invoice(): void
    {
        $residence = Residence::factory()->create();
        $subscription = Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Starter]);

        $this->artisan('subscriptions:activate', ['residence' => $residence->id, 'cycle' => 'monthly'])
            ->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertEqualsWithDelta(30, Carbon::now()->diffInDays($subscription->current_period_end), 1);

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(50, $invoice->amount);
        $this->assertSame(SubscriptionPlan::Starter, $invoice->plan);
    }

    public function test_activate_command_applies_the_annual_discount(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Standard]);

        $this->artisan('subscriptions:activate', ['residence' => $residence->id, 'cycle' => 'annual'])
            ->assertSuccessful();

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('residence_id', $residence->id)->first();
        // 100 DH/month x 12 x 0.8 = 960 DH
        $this->assertSame(960, $invoice->amount);
    }

    public function test_activate_command_requires_an_explicit_amount_for_the_custom_plan(): void
    {
        $residence = Residence::factory()->create();
        Subscription::factory()->for($residence)->trial()->create(['plan' => SubscriptionPlan::Custom]);

        $this->artisan('subscriptions:activate', ['residence' => $residence->id, 'cycle' => 'monthly'])
            ->assertFailed();

        $this->artisan('subscriptions:activate', [
            'residence' => $residence->id,
            'cycle' => 'monthly',
            '--amount' => 500,
        ])->assertSuccessful();

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('residence_id', $residence->id)->first();
        $this->assertSame(500, $invoice->amount);
    }

    public function test_invoices_endpoint_returns_only_the_authenticated_residences_invoices(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        $subscriptionA = Subscription::factory()->for($residenceA)->active()->create();
        $subscriptionB = Subscription::factory()->for($residenceB)->active()->create();

        SubscriptionInvoice::factory()->for($residenceA)->for($subscriptionA)->create(['amount' => 50]);
        SubscriptionInvoice::factory()->for($residenceB)->for($subscriptionB)->create(['amount' => 100]);

        $response = $this->actingAs($adminA)->getJson('/api/subscription/invoices');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 50);
    }
}
