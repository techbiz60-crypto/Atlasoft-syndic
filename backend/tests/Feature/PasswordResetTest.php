<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_reset_link_sends_the_branded_notification(): void
    {
        Notification::fake();

        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['email' => 'admin@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'admin@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * The whole point of this response being identical: an attacker (or a
     * curious ex-tenant) can't use this endpoint to find out who has an
     * account.
     */
    public function test_requesting_a_reset_link_for_an_unknown_email_gives_the_same_response(): void
    {
        Notification::fake();

        $unknown = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com']);
        $unknown->assertOk();

        $residence = Residence::factory()->create();
        User::factory()->for($residence)->create(['email' => 'real@example.com']);
        $known = $this->postJson('/api/forgot-password', ['email' => 'real@example.com']);

        $this->assertSame($unknown->json('message'), $known->json('message'));
    }

    public function test_a_valid_token_actually_resets_the_password_and_the_user_can_log_in_with_it(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['email' => 'admin@example.com', 'password' => 'old-password']);

        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'brand-new-password'])
            ->assertOk();
    }

    public function test_an_invalid_token_is_rejected_and_the_password_is_unchanged(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['email' => 'admin@example.com', 'password' => 'old-password']);

        $this->postJson('/api/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'admin@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_reset_notification_localizes_the_email_by_the_users_locale(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['email' => 'karim@example.com', 'locale' => 'ar']);

        $notification = new ResetPasswordNotification('a-fake-token');
        $mail = $notification->toMail($user);

        $this->assertStringContainsString('إعادة تعيين', $mail->envelope()->subject);
    }

    public function test_the_reset_link_points_to_the_frontend_not_a_backend_route(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['email' => 'admin@example.com']);

        $notification = new ResetPasswordNotification('a-fake-token');
        $mail = $notification->toMail($user);

        $this->assertStringStartsWith(config('app.frontend_url').'/reset-password?token=', $mail->resetUrl);
    }
}
