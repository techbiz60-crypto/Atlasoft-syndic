<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Payment;
use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_a_complete_export(): void
    {
        Storage::fake();

        $residence = Residence::factory()->create(['name' => 'Résidence Test']);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create();
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(500)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create(['owner_name' => 'Fatima Zahra']);
        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 500]);
        Payment::factory()->for($residence)->for($fundCall)->create(['amount' => 500]);

        $expenseCategory = ExpenseCategory::factory()->for($residence)->create(['name' => 'Entretien']);
        Expense::factory()->for($residence)->for($expenseCategory, 'category')->create([
            'label' => 'Facture ascenseur',
            'receipt_path' => UploadedFile::fake()->create('facture.pdf')->store(),
        ]);

        $revenueCategory = RevenueCategory::factory()->for($residence)->create();
        Revenue::factory()->for($residence)->for($revenueCategory, 'category')->create();

        $response = $this->actingAs($admin)->get('/api/export');

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));

        // Save the streamed response to disk so it can actually be opened —
        // the only way to confirm this is a real, valid zip/xlsx and not
        // just a 200 with the right header.
        $zipPath = tempnam(sys_get_temp_dir(), 'export-test-').'.zip';
        file_put_contents($zipPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('donnees.xlsx'));
        $this->assertNotFalse($zip->locateName('justificatifs/depenses/'.now()->format('Y-m-d').'-facture-ascenseur-1.pdf'));

        $xlsxPath = tempnam(sys_get_temp_dir(), 'export-test-').'.xlsx';
        file_put_contents($xlsxPath, $zip->getFromName('donnees.xlsx'));
        $zip->close();

        $spreadsheet = IOFactory::load($xlsxPath);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->assertSame(['Appartements', 'Cotisations', 'Paiements', 'Dépenses', 'Recettes'], $sheetNames);
        $this->assertSame('Fatima Zahra', $spreadsheet->getSheetByName('Appartements')->getCell('E2')->getValue());
        $this->assertSame(500, $spreadsheet->getSheetByName('Paiements')->getCell('D2')->getValue());

        unlink($zipPath);
        unlink($xlsxPath);
    }

    public function test_conseil_member_cannot_export(): void
    {
        $residence = Residence::factory()->create();
        $member = User::factory()->for($residence)->conseil()->create();

        $this->actingAs($member)->get('/api/export')->assertForbidden();
    }

    public function test_export_works_even_when_the_subscription_is_expired(): void
    {
        $residence = Residence::factory()->create();
        $admin = User::factory()->for($residence)->create();
        Subscription::factory()->for($residence)->expired()->create();

        // Sanity check: an expired subscription really does block a write.
        $this->actingAs($admin)->postJson('/api/buildings', ['name' => 'Bâtiment B'])->assertStatus(403);

        // But never the export — that's the whole point.
        $this->actingAs($admin)->get('/api/export')->assertOk();
    }
}
