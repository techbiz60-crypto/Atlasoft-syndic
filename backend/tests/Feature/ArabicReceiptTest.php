<?php

namespace Tests\Feature;

use App\Actions\Pdf\ShapeArabicText;
use App\Models\Building;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArabicReceiptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pulls the text drawing operators out of a PDF's content stream, which
     * is where a missing glyph shows up as "?".
     */
    private function pdfTextOperators(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        $text = '';

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            $text .= $decoded === false ? $stream : $decoded;
        }

        return $text;
    }

    public function test_latin_text_is_left_untouched(): void
    {
        $shaper = app(ShapeArabicText::class);

        // Reversing these would be as wrong as not shaping Arabic.
        $this->assertSame('Sahel Oued 5', $shaper->handle('Sahel Oued 5'));
        $this->assertSame('A12', $shaper->handle('A12'));
        $this->assertSame('', $shaper->handle(null));
    }

    public function test_arabic_is_converted_to_presentation_forms(): void
    {
        $shaper = app(ShapeArabicText::class);

        $shaped = $shaper->handle('محمد القدوري');

        $this->assertNotSame('محمد القدوري', $shaped);
        // The shaped output must live in the presentation-form blocks, which
        // is what DejaVu Sans can actually draw joined.
        $this->assertMatchesRegularExpression('/[\x{FB50}-\x{FEFF}]/u', $shaped);
    }

    public function test_a_receipt_pdf_carries_arabic_glyphs_rather_than_question_marks(): void
    {
        $residence = Residence::factory()->create(['name' => 'إقامة الفردوس']);
        $admin = User::factory()->for($residence)->create();
        $building = Building::factory()->for($residence)->create(['name' => 'Sahel Oued 5']);
        $lotType = LotType::factory()->for($residence)->withMonthlyAmount(200)->create();
        $lot = Lot::factory()->for($residence)->for($building)->for($lotType)->create([
            'number' => '16',
            'owner_name' => 'محمد القدوري',
        ]);

        $fundCall = FundCall::factory()->for($residence)->for($lot)->create(['amount' => 200]);
        $payment = $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => 200,
            'paid_at' => now(),
            'method' => PaymentMethod::Virement,
        ]);

        $response = $this->actingAs($admin)
            ->get("/api/fund-calls/{$fundCall->id}/payments/{$payment->id}/receipt");

        $response->assertOk();

        $operators = $this->pdfTextOperators($response->getContent());

        // The bug this guards: with a core font and unshaped text, every
        // Arabic letter reached the PDF as "?".
        $this->assertStringNotContainsString('(????)', $operators);
        $this->assertStringNotContainsString('(???????)', $operators);
    }
}
