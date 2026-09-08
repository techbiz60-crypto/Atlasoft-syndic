<?php

namespace Tests\Feature\Auth;

use App\Models\Building;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_own_password(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertNoContent();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'something-else',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_the_new_password_must_be_at_least_eight_characters(): void
    {
        $residence = Residence::factory()->create();
        $user = User::factory()->for($residence)->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'old-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_a_resident_can_change_their_own_password_too(): void
    {
        $residence = Residence::factory()->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create();
        $resident = User::factory()->for($residence)->coproprietaire()->create([
            'lot_id' => $lot->id,
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($resident)->putJson('/api/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertNoContent();
    }
}
