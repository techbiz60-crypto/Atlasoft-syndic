<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_endpoint_never_exposes_another_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        User::factory()->for($residenceB)->create();

        $response = $this->actingAs($adminA)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.residence_id', $residenceA->id)
            ->assertJsonMissing(['residence_id' => $residenceB->id]);
    }

    public function test_user_lookup_by_email_ignores_the_currently_authenticated_users_residence(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();

        $adminA = User::factory()->for($residenceA)->create();
        $memberB = User::factory()->for($residenceB)->conseil()->create();

        $this->actingAs($adminA);

        $found = User::where('email', $memberB->email)->first();

        $this->assertNotNull($found);
        $this->assertSame($memberB->id, $found->id);
    }
}
