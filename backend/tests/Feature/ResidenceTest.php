<?php

namespace Tests\Feature;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_residence_settings(): void
    {
        $residence = Residence::factory()->create(['bank_rib' => null]);
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->putJson('/api/residence', [
            'bank_rib' => '007780000123456789012345',
        ]);

        $response->assertOk()->assertJsonPath('data.bank_rib', '007780000123456789012345');
    }

    public function test_conseil_member_cannot_update_residence_settings(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)
            ->putJson('/api/residence', ['bank_rib' => '007780000123456789012345'])
            ->assertForbidden();
    }

    public function test_coproprietaire_can_view_residence_but_not_edit_it(): void
    {
        $residence = Residence::factory()->create();
        $owner = User::factory()->for($residence)->coproprietaire()->create();

        $this->actingAs($owner)->getJson('/api/residence')
            ->assertOk()
            ->assertJsonPath('data.id', $residence->id);

        $this->actingAs($owner)
            ->putJson('/api/residence', ['name' => 'Hacked'])
            ->assertForbidden();
    }
}
