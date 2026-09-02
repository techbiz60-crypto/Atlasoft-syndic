<?php

namespace Tests\Feature\Auth;

use App\Models\Residence;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_a_residence_and_an_admin_user(): void
    {
        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Al Andalous',
            'lots_count' => 24,
            'name' => 'Fatima Zahra',
            'email' => 'fatima@example.com',
            'whatsapp_number' => '+212600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('residences', [
            'name' => 'Résidence Al Andalous',
            'lots_count' => 24,
        ]);

        $residence = Residence::where('name', 'Résidence Al Andalous')->first();
        $this->assertDatabaseHas('buildings', [
            'residence_id' => $residence->id,
            'name' => 'Bâtiment principal',
        ]);

        $user = User::where('email', 'fatima@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertNotNull($user->residence_id);
    }

    public function test_registration_requires_all_mandatory_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['residence_name', 'lots_count', 'name', 'email', 'whatsapp_number', 'password']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'residence_name' => 'Résidence Test',
            'lots_count' => 10,
            'name' => 'Test',
            'email' => 'taken@example.com',
            'whatsapp_number' => '+212600000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
