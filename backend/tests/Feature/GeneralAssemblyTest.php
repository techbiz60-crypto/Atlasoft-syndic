<?php

namespace Tests\Feature;

use App\Models\GeneralAssembly;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralAssemblyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_the_date_an_exercises_ag_was_held(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->putJson('/api/general-assemblies/2026', ['held_on' => '2027-01-31']);

        $response->assertOk()->assertJsonPath('data.exercise_year', 2026);
        $this->assertDatabaseHas('general_assemblies', [
            'residence_id' => $residence->id,
            'exercise_year' => 2026,
            'held_on' => '2027-01-31',
        ]);
    }

    public function test_saving_the_same_year_again_updates_it_instead_of_duplicating(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->putJson('/api/general-assemblies/2026', ['held_on' => '2027-01-31'])->assertOk();
        $this->actingAs($admin)->putJson('/api/general-assemblies/2026', ['held_on' => '2027-02-15'])->assertOk();

        $this->assertDatabaseCount('general_assemblies', 1);
        $this->assertDatabaseHas('general_assemblies', ['exercise_year' => 2026, 'held_on' => '2027-02-15']);
    }

    public function test_admin_can_list_and_delete_recorded_dates(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        GeneralAssembly::factory()->for($residence)->create(['exercise_year' => 2025, 'held_on' => '2026-02-10']);
        GeneralAssembly::factory()->for($residence)->create(['exercise_year' => 2026, 'held_on' => '2027-01-31']);

        $this->actingAs($admin)->getJson('/api/general-assemblies')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.exercise_year', 2026);

        $this->actingAs($admin)->deleteJson('/api/general-assemblies/2025')->assertNoContent();
        $this->assertDatabaseMissing('general_assemblies', ['exercise_year' => 2025]);
    }

    public function test_conseil_member_cannot_manage_ag_dates_but_can_read_them(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->putJson('/api/general-assemblies/2026', ['held_on' => '2027-01-31'])->assertForbidden();
        $this->actingAs($member)->deleteJson('/api/general-assemblies/2026')->assertForbidden();
        // Read access is intentionally open to every role — the missing-AG
        // reminder banner (admin/trésorier) relies on it, and the dates
        // themselves aren't sensitive.
        $this->actingAs($member)->getJson('/api/general-assemblies')->assertOk();
    }

    public function test_admin_can_save_convocation_details_alongside_the_date(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $response = $this->actingAs($admin)->putJson('/api/general-assemblies/2026', [
            'held_on' => '2027-01-31',
            'location' => 'Salle des fêtes, rez-de-chaussée',
            'meeting_time' => '18:30',
            'agenda' => ['Approbation des comptes 2026', 'Vote du budget 2027', 'Élection du conseil syndical'],
            'convocation_sent_at' => '2027-01-10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.location', 'Salle des fêtes, rez-de-chaussée')
            ->assertJsonPath('data.meeting_time', '18:30')
            ->assertJsonPath('data.agenda', ['Approbation des comptes 2026', 'Vote du budget 2027', 'Élection du conseil syndical']);
    }

    public function test_convocation_pdf_can_be_downloaded_once_an_ag_is_recorded(): void
    {
        $residence = Residence::factory()->create(['name' => 'Résidence Test']);
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->putJson('/api/general-assemblies/2026', [
            'held_on' => '2027-01-31',
            'location' => 'Salle commune',
            'meeting_time' => '18:00',
            'agenda' => ['Approbation des comptes 2026'],
        ])->assertOk();

        $response = $this->actingAs($admin)->get('/api/general-assemblies/2026/convocation');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_convocation_download_404s_when_no_ag_is_recorded_for_that_year(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();

        $this->actingAs($admin)->get('/api/general-assemblies/2026/convocation')->assertNotFound();
    }

    public function test_conseil_member_can_download_the_convocation(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($admin)->putJson('/api/general-assemblies/2026', ['held_on' => '2027-01-31'])->assertOk();

        $this->actingAs($member)->get('/api/general-assemblies/2026/convocation')->assertOk();
    }

    public function test_ag_dates_never_leak_across_residences(): void
    {
        $residenceA = Residence::factory()->create();
        $residenceB = Residence::factory()->create();
        $adminA = User::factory()->for($residenceA)->create();
        GeneralAssembly::factory()->for($residenceB)->create(['exercise_year' => 2026, 'held_on' => '2027-01-31']);

        $this->actingAs($adminA)->getJson('/api/general-assemblies')->assertOk()->assertJsonCount(0, 'data');

        // Deleting a year that only exists for another residence must be a
        // silent no-op, not a way to discover or affect it.
        $this->actingAs($adminA)->deleteJson('/api/general-assemblies/2026')->assertNoContent();
        $this->assertDatabaseHas('general_assemblies', ['residence_id' => $residenceB->id, 'exercise_year' => 2026]);
    }
}
