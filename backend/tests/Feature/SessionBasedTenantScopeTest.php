<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression test: TenantScope must not recurse infinitely when the
 * authenticated user is resolved from a real session cookie (as opposed
 * to `actingAs()`, which bypasses the database lookup entirely and would
 * never have caught this).
 */
class SessionBasedTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_scoped_query_does_not_crash_when_the_user_is_resolved_from_a_real_session(): void
    {
        $residence = Residence::factory()->create();
        User::factory()->for($residence)->create([
            'email' => 'session-admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $client = $this->withHeader('Referer', 'http://localhost:5173');

        $loginResponse = $client->postJson('/api/login', [
            'email' => 'session-admin@example.com',
            'password' => 'password123',
        ])->assertOk();

        $sessionCookie = $loginResponse->headers->getCookies()[0];

        $client->withUnencryptedCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->getJson('/api/user')
            ->assertOk();

        $client->withUnencryptedCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->getJson('/api/lot-types')
            ->assertOk();
    }
}
