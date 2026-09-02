<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $client = $this->withHeader('Referer', 'http://localhost:5173');

        $loginResponse = $client->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertOk();

        $sessionCookie = $loginResponse->headers->getCookies()[0];

        $client->withUnencryptedCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->postJson('/api/logout')
            ->assertNoContent();

        // Note: the "auth:sanctum" middleware sets 'sanctum' as the default guard for the
        // remainder of the request lifecycle once it authenticates. Since our logout only
        // logs out the underlying "web" session guard, we must assert against it explicitly
        // rather than the (now-default) "sanctum" guard.
        $this->assertGuest('web');
    }

    public function test_guest_cannot_access_protected_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
