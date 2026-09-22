<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use ZipArchive;

/**
 * The self-service "get everything out" button — every SaaS worth trusting
 * offers this independently of cancelling, not as a one-time favor when
 * someone leaves. One Excel workbook (one sheet per data type, so it opens
 * as a single file) plus the real uploaded justificatifs (expense/revenue
 * receipts), zipped together.
 *
 * Deliberately not gated behind subscription.active: a residence whose
 * subscription just got deactivated — exactly the moment someone most
 * wants their data out — must still be able to reach this.
 */
class ExportController extends Controller
{
    public function full(Request $request): BinaryFileResponse
    {
        $residence = $request->user()->residence;

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->addLotsSheet($spreadsheet);
        $this->addFundCallsSheet($spreadsheet);
        $this->addPaymentsSheet($spreadsheet);
        $this->addExpensesSheet($spreadsheet);
        $this->addRevenuesSheet($spreadsheet);

        $tmpDir = sys_get_temp_dir();
        $xlsxPath = tempnam($tmpDir, 'atlasoft-export-').'.xlsx';
        (new Xlsx($spreadsheet))->save($xlsxPath);

        $zipPath = tempnam($tmpDir, 'atlasoft-export-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($xlsxPath, 'donnees.xlsx');

        $this->addJustificatifs($zip, Expense::with('category')->get(), 'justificatifs/depenses');
        $this->addJustificatifs($zip, Revenue::with('category')->get(), 'justificatifs/recettes');

        $zip->close();
        unlink($xlsxPath);

        $filename = 'export-'.str($residence->name)->slug().'-'.now()->format('Y-m-d').'.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend()->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );
    }

    private function addLotsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Appartements');
        $sheet->fromArray(['Numéro', 'Immeuble', 'Type de lot', 'Étage', 'Propriétaire', 'Téléphone', 'Email'], null, 'A1');

        $row = 2;
        foreach (Lot::with(['building', 'lotType'])->orderBy('number')->get() as $lot) {
            $sheet->fromArray([
                $lot->number,
                $lot->building->name,
                $lot->lotType->name,
                $lot->floor,
                $lot->owner_name,
                $lot->owner_phone,
                $lot->owner_email,
            ], null, "A{$row}");
            $row++;
        }
    }

    private function addFundCallsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cotisations');
        $sheet->fromArray(['Appartement', 'Immeuble', 'Période', 'Solde d\'ouverture', 'Montant', 'Payé', 'Statut'], null, 'A1');

        $row = 2;
        foreach (FundCall::with(['lot.building'])->orderByDesc('period')->get() as $call) {
            $sheet->fromArray([
                $call->lot->number,
                $call->lot->building->name,
                $call->period->format('Y-m-d'),
                $call->is_opening_balance ? 'Oui' : 'Non',
                $call->amount,
                $call->paid_amount,
                $call->status,
            ], null, "A{$row}");
            $row++;
        }
    }

    private function addPaymentsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Paiements');
        $sheet->fromArray(['Date', 'Appartement', 'Immeuble', 'Montant', 'Mode', 'Notes'], null, 'A1');

        $row = 2;
        foreach (Payment::with('fundCall.lot.building')->orderByDesc('paid_at')->get() as $payment) {
            $sheet->fromArray([
                $payment->paid_at->format('Y-m-d'),
                $payment->fundCall->lot->number,
                $payment->fundCall->lot->building->name,
                $payment->amount,
                $payment->method->value,
                $payment->notes,
            ], null, "A{$row}");
            $row++;
        }
    }

    private function addExpensesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Dépenses');
        $sheet->fromArray(['Date', 'Catégorie', 'Libellé', 'Montant', 'Mode'], null, 'A1');

        $row = 2;
        foreach (Expense::with('category')->orderByDesc('paid_at')->get() as $expense) {
            $sheet->fromArray([
                $expense->paid_at->format('Y-m-d'),
                $expense->category->name,
                $expense->label,
                $expense->amount,
                $expense->method->value,
            ], null, "A{$row}");
            $row++;
        }
    }

    private function addRevenuesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Recettes');
        $sheet->fromArray(['Date', 'Catégorie', 'Montant', 'Mode'], null, 'A1');

        $row = 2;
        foreach (Revenue::with('category')->orderByDesc('received_at')->get() as $revenue) {
            $sheet->fromArray([
                $revenue->received_at->format('Y-m-d'),
                $revenue->category->name,
                $revenue->amount,
                $revenue->method->value,
            ], null, "A{$row}");
            $row++;
        }
    }

    /**
     * @param  Collection<int, Expense|Revenue>  $records
     */
    private function addJustificatifs(ZipArchive $zip, $records, string $folder): void
    {
        foreach ($records as $record) {
            if (! $record->receipt_path || ! Storage::exists($record->receipt_path)) {
                continue;
            }

            $extension = pathinfo($record->receipt_path, PATHINFO_EXTENSION);
            $date = ($record->paid_at ?? $record->received_at)?->format('Y-m-d') ?? 'sans-date';
            $label = str($record->label ?? $record->category->name)->slug();

            $zip->addFromString(
                "{$folder}/{$date}-{$label}-{$record->id}.{$extension}",
                Storage::get($record->receipt_path),
            );
        }
    }
}
