<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email_and_leaves_the_account_unverified(): void
    {
        Notification::fake();

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
        $this->assertNull($response->json('user.email_verified_at'));

        $user = User::where('email', 'admin@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_app_routes_are_blocked_until_the_email_is_verified(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->unverified()->create();

        $this->actingAs($admin)->getJson('/api/buildings')->assertForbidden();
    }

    public function test_user_and_logout_and_resend_endpoints_stay_open_while_unverified(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->unverified()->create();

        $this->actingAs($admin)->getJson('/api/user')->assertOk();
        $this->actingAs($admin)->postJson('/api/email/verification-notification')->assertOk();
        $this->actingAs($admin)->withHeader('Referer', 'http://localhost:5173')->postJson('/api/logout')->assertNoContent();
    }

    public function test_verified_users_can_access_app_routes(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->getJson('/api/buildings')->assertOk();
    }

    public function test_the_signed_verification_link_marks_the_email_as_verified_and_redirects_to_the_frontend(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/login?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_tampered_hash_is_rejected(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('not-the-real-email@example.com'),
        ]);

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_verification_url_is_rejected(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->unverified()->create();

        $this->get("/email/verify/{$user->id}/".sha1($user->email))->assertForbidden();
    }

    public function test_resending_when_already_verified_does_not_send_a_new_notification(): void
    {
        Notification::fake();

        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)
            ->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Votre adresse email est déjà vérifiée.');

        Notification::assertNothingSent();
    }
}
