<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreBulkPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentBatchRequest;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotTypeRate;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Payments created together in one bulk action (§bulk) share a batch_id
     * and are grouped into a single row here — a copropriétaire paying 6
     * months at once should see one transaction, not 6, even though under
     * the hood each month still has its own FundCall + Payment record so
     * the Cotisations grid keeps its per-month status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['fundCall.lot.building'])->orderByDesc('id');

        if ($request->filled('year')) {
            $query->whereYear('paid_at', $request->integer('year'));
        }

        if ($request->filled('building_id')) {
            $query->whereHas('fundCall.lot', fn ($q) => $q->where('building_id', $request->integer('building_id')));
        }

        $grouped = $query->get()
            ->groupBy(fn (Payment $payment) => $payment->batch_id ?? 'single-'.$payment->id)
            ->map(function ($payments) {
                $first = $payments->first();

                return [
                    'id' => $first->id,
                    'fund_call_id' => $first->fund_call_id,
                    'batch_id' => $first->batch_id,
                    'amount' => $payments->sum('amount'),
                    'paid_at' => $first->paid_at,
                    'method' => $first->method,
                    'notes' => $first->notes,
                    'lot' => [
                        'id' => $first->fundCall->lot->id,
                        'number' => $first->fundCall->lot->number,
                        // Frozen at the time of payment, so a later change of
                        // ownership doesn't rewrite who actually paid.
                        'owner_name' => $first->owner_name ?? $first->fundCall->lot->owner_name,
                        'building' => ['id' => $first->fundCall->lot->building->id, 'name' => $first->fundCall->lot->building->name],
                    ],
                    'periods' => $payments->pluck('fundCall.period')->sort()->values(),
                    'is_opening_balance' => $first->fundCall->is_opening_balance,
                ];
            })
            ->sortByDesc('id')
            ->values();

        return response()->json(['data' => $grouped]);
    }

    public function store(StorePaymentRequest $request, FundCall $fundCall): JsonResponse
    {
        $payment = $fundCall->payments()->create($request->validated());

        return response()->json(['data' => $payment, 'fund_call' => $fundCall->fresh(['lot.building', 'lot.lotType', 'payments'])], 201);
    }

    public function update(StorePaymentRequest $request, FundCall $fundCall, Payment $payment): JsonResponse
    {
        abort_unless($payment->fund_call_id === $fundCall->id, 404);

        $payment->update($request->validated());

        return response()->json(['data' => $payment, 'fund_call' => $fundCall->fresh(['lot.building', 'lot.lotType', 'payments'])]);
    }

    public function destroy(FundCall $fundCall, Payment $payment): JsonResponse
    {
        abort_unless($payment->fund_call_id === $fundCall->id, 404);

        $payment->delete();

        return response()->json(status: 204);
    }

    public function receipt(Request $request, FundCall $fundCall, Payment $payment): Response
    {
        abort_unless($payment->fund_call_id === $fundCall->id, 404);

        $fundCall->load('lot.building');

        $pdf = Pdf::loadView('receipts.payment', [
            'payment' => $payment,
            'fundCall' => $fundCall,
            'lot' => $fundCall->lot,
            'residence' => $request->user()->residence,
            'methodLabel' => $payment->method->label(),
        ]);

        return $pdf->stream("recu-paiement-{$payment->id}.pdf");
    }

    /**
     * Fully replaces a grouped payment: deletes its current payment rows and
     * re-settles the (possibly different) selected months from scratch, at
     * each month's current rate. A deselected month simply reverts to
     * unpaid/partial rather than being deleted — it's still genuinely due,
     * only this transaction's contribution to it is being undone.
     */
    public function updateBatch(UpdatePaymentBatchRequest $request, string $batchId): JsonResponse
    {
        $existingPayments = Payment::with('fundCall.lot')->where('batch_id', $batchId)->get();

        abort_if($existingPayments->isEmpty(), 404);

        $lot = $existingPayments->first()->fundCall->lot;
        $year = $request->integer('year');

        $result = DB::transaction(function () use ($existingPayments, $lot, $year, $request, $batchId) {
            Payment::whereIn('id', $existingPayments->pluck('id'))->delete();

            return $this->settleSelectedMonths($lot, $year, $request->input('months'), $request, $batchId);
        });

        return response()->json([
            'message' => "{$result['months_settled']} mois soldé(s) pour {$year}.",
            'months_settled' => $result['months_settled'],
        ]);
    }

    public function destroyBatch(string $batchId): JsonResponse
    {
        $payments = Payment::where('batch_id', $batchId)->get();

        abort_if($payments->isEmpty(), 404);

        Payment::whereIn('id', $payments->pluck('id'))->delete();

        return response()->json(status: 204);
    }

    public function batchReceipt(Request $request, string $batchId): Response
    {
        $payments = Payment::with('fundCall.lot.building')->where('batch_id', $batchId)->orderBy('id')->get();

        abort_if($payments->isEmpty(), 404);

        $first = $payments->first();

        $pdf = Pdf::loadView('receipts.payment-batch', [
            'payments' => $payments,
            'lot' => $first->fundCall->lot,
            'residence' => $request->user()->residence,
            'methodLabel' => $first->method->label(),
            'totalAmount' => $payments->sum('amount'),
            'paidAt' => $first->paid_at,
            'notes' => $first->notes,
        ]);

        return $pdf->stream("recu-paiement-groupe-{$batchId}.pdf");
    }

    /**
     * Record a lump-sum payment for a lot, either by:
     * - amount: automatically spread across outstanding fund calls for the
     *   given year, oldest month first, generating missing months as the
     *   amount reaches them (a partial amount never bills a month it doesn't
     *   fully cover); or
     * - months: settling exactly the months the admin picked, in full,
     *   generating missing fund calls for months not yet billed.
     */
    public function bulk(StoreBulkPaymentRequest $request, Lot $lot): JsonResponse
    {
        $year = $request->integer('year');
        $batchId = (string) Str::uuid();

        $result = $request->has('months')
            ? $this->settleSelectedMonths($lot, $year, $request->input('months'), $request, $batchId)
            : $this->settleByAmount($lot, $year, $request->integer('amount'), $request, $batchId);

        return response()->json([
            'message' => $result['unallocated'] > 0
                ? "{$result['months_settled']} mois soldé(s). {$result['unallocated']} DH n'ont pas suffi pour couvrir un mois de plus et n'ont pas été affectés."
                : "{$result['months_settled']} mois soldé(s) pour {$year}.",
            'unallocated' => $result['unallocated'],
            'months_settled' => $result['months_settled'],
        ], 201);
    }

    /**
     * @return array{months_settled: int, unallocated: int}
     */
    private function settleByAmount(Lot $lot, int $year, int $remainingAmount, StoreBulkPaymentRequest $request, string $batchId): array
    {
        return DB::transaction(function () use ($lot, $year, $remainingAmount, $request, $batchId) {
            $allCallsThisYear = $lot->fundCalls()
                ->whereYear('period', $year)
                ->orderBy('period')
                ->get();

            $existingMonths = $allCallsThisYear->map(fn (FundCall $call) => $call->period->month)->all();
            $outstandingCalls = $allCallsThisYear->filter(fn (FundCall $call) => $call->status !== 'paid');

            $monthsSettled = 0;

            foreach ($outstandingCalls as $fundCall) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $due = $fundCall->amount - $fundCall->paid_amount;
                $allocated = min($due, $remainingAmount);

                $fundCall->payments()->create([
                    'residence_id' => $lot->residence_id,
                    'batch_id' => $batchId,
                    'amount' => $allocated,
                    'paid_at' => $request->date('paid_at'),
                    'method' => $request->input('method'),
                    'notes' => $request->input('notes'),
                ]);

                $remainingAmount -= $allocated;
                $monthsSettled++;
            }

            if ($remainingAmount > 0) {
                for ($month = 1; $month <= 12 && $remainingAmount > 0; $month++) {
                    if (in_array($month, $existingMonths, true)) {
                        continue;
                    }

                    $period = Carbon::create($year, $month, 1);
                    $rate = $this->rateFor($lot, $period);

                    if (! $rate || $remainingAmount < $rate->amount) {
                        break;
                    }

                    $fundCall = FundCall::withoutGlobalScopes()->create([
                        'residence_id' => $lot->residence_id,
                        'lot_id' => $lot->id,
                        'amount' => $rate->amount,
                        'period' => $period,
                    ]);

                    $fundCall->payments()->create([
                        'residence_id' => $lot->residence_id,
                        'batch_id' => $batchId,
                        'amount' => $rate->amount,
                        'paid_at' => $request->date('paid_at'),
                        'method' => $request->input('method'),
                        'notes' => $request->input('notes'),
                    ]);

                    $remainingAmount -= $rate->amount;
                    $monthsSettled++;
                }
            }

            return ['months_settled' => $monthsSettled, 'unallocated' => $remainingAmount];
        });
    }

    /**
     * @param  array<int, int>  $months
     * @return array{months_settled: int, unallocated: int}
     */
    private function settleSelectedMonths(Lot $lot, int $year, array $months, Request $request, string $batchId): array
    {
        return DB::transaction(function () use ($lot, $year, $months, $request, $batchId) {
            $existingCalls = $lot->fundCalls()
                ->whereYear('period', $year)
                ->get()
                ->keyBy(fn (FundCall $call) => $call->period->month);

            $monthsSettled = 0;

            foreach ($months as $month) {
                $fundCall = $existingCalls->get($month);

                if (! $fundCall) {
                    $period = Carbon::create($year, $month, 1);
                    $rate = $this->rateFor($lot, $period);

                    if (! $rate) {
                        continue;
                    }

                    $fundCall = FundCall::withoutGlobalScopes()->create([
                        'residence_id' => $lot->residence_id,
                        'lot_id' => $lot->id,
                        'amount' => $rate->amount,
                        'period' => $period,
                    ]);
                }

                $due = $fundCall->amount - $fundCall->paid_amount;

                if ($due <= 0) {
                    continue;
                }

                $fundCall->payments()->create([
                    'residence_id' => $lot->residence_id,
                    'batch_id' => $batchId,
                    'amount' => $due,
                    'paid_at' => $request->date('paid_at'),
                    'method' => $request->input('method'),
                    'notes' => $request->input('notes'),
                ]);

                $monthsSettled++;
            }

            return ['months_settled' => $monthsSettled, 'unallocated' => 0];
        });
    }

    private function rateFor(Lot $lot, Carbon $period): ?LotTypeRate
    {
        return LotTypeRate::withoutGlobalScopes()
            ->where('lot_type_id', $lot->lot_type_id)
            ->whereDate('effective_date', '<=', $period)
            ->orderByDesc('effective_date')
            ->first();
    }
}
