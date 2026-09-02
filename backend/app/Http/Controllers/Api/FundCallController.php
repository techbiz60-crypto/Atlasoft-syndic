<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FundCall\MatrixRequest;
use App\Http\Requests\FundCall\StoreFundCallRequest;
use App\Http\Requests\FundCall\UpdateOpeningBalanceRequest;
use App\Models\FundCall;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class FundCallController extends Controller
{
    public function matrix(MatrixRequest $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;

        $lots = Lot::with(['building', 'lotType.rates', 'openingBalance', 'fundCalls' => function ($query) use ($year) {
            $query->whereYear('period', $year)->where('is_opening_balance', false)->with('payments');
        }])
            ->when($request->filled('building_id'), fn ($query) => $query->where('building_id', $request->integer('building_id')))
            ->orderBy('number')
            ->get();

        $data = $lots->map(function (Lot $lot) use ($year) {
            $byMonth = $lot->fundCalls->keyBy(fn (FundCall $call) => $call->period->month);

            $months = collect(range(1, 12))->map(function (int $month) use ($byMonth, $lot, $year) {
                $call = $byMonth->get($month);

                // For months not yet billed, project the amount from the lot's current
                // rate so the UI (e.g. the bulk-payment month picker) can show a total
                // before the fund call actually exists.
                $amount = $call?->amount ?? $lot->lotType->rateAt(Carbon::create($year, $month, 1))?->amount;

                return [
                    'month' => $month,
                    'fund_call_id' => $call?->id,
                    'amount' => $amount,
                    'paid_amount' => $call?->paid_amount ?? 0,
                    'status' => $call?->status ?? 'none',
                ];
            });

            return [
                'lot_id' => $lot->id,
                'lot_number' => $lot->number,
                'building_name' => $lot->building->name,
                'owner_name' => $lot->owner_name,
                'months' => $months,
                'opening_balance' => $lot->openingBalance ? [
                    'fund_call_id' => $lot->openingBalance->id,
                    'amount' => $lot->openingBalance->amount,
                    'paid_amount' => $lot->openingBalance->paid_amount,
                    'status' => $lot->openingBalance->status,
                ] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function unpaid(): JsonResponse
    {
        $lots = Lot::with(['building', 'fundCalls.payments'])->get();

        $unpaid = $lots
            ->map(function (Lot $lot) {
                $outstandingCalls = $lot->fundCalls->filter(fn (FundCall $call) => $call->status !== 'paid');

                if ($outstandingCalls->isEmpty()) {
                    return null;
                }

                $lastPayment = $lot->fundCalls
                    ->flatMap(fn (FundCall $call) => $call->payments)
                    ->sortByDesc('paid_at')
                    ->first();

                return [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->number,
                    'building_name' => $lot->building->name,
                    'owner_name' => $lot->owner_name,
                    'owner_phone' => $lot->owner_phone,
                    'total_due' => $outstandingCalls->sum(fn (FundCall $call) => $call->amount - $call->paid_amount),
                    'months_late' => $outstandingCalls->count(),
                    'oldest_unpaid_period' => $outstandingCalls->min('period')?->toDateString(),
                    'last_payment_date' => $lastPayment?->paid_at->toDateString(),
                    'opening_balance_due' => $outstandingCalls
                        ->filter(fn (FundCall $call) => $call->is_opening_balance)
                        ->sum(fn (FundCall $call) => $call->amount - $call->paid_amount),
                ];
            })
            ->filter()
            ->sortByDesc('months_late')
            ->values();

        return response()->json(['data' => $unpaid]);
    }

    public function show(FundCall $fundCall): JsonResponse
    {
        return response()->json(['data' => $fundCall->load(['lot.building', 'lot.lotType', 'payments'])]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = FundCall::with(['lot.building', 'lot.lotType', 'payments'])->orderByDesc('period');

        if ($request->filled('period')) {
            $query->whereDate('period', $request->date('period'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreFundCallRequest $request): JsonResponse
    {
        $fundCall = FundCall::create($request->validated());

        return response()->json(['data' => $fundCall->load(['lot.building', 'lot.lotType', 'payments'])], 201);
    }

    public function generate(Request $request): JsonResponse
    {
        $period = $request->date('period')?->startOfMonth()->toDateString();

        Artisan::call('fund-calls:generate', array_filter([
            '--residence' => $request->user()->residence_id,
            '--period' => $period,
        ]));

        return response()->json(['message' => trim(Artisan::output())]);
    }

    public function destroy(FundCall $fundCall): JsonResponse
    {
        $fundCall->delete();

        return response()->json(status: 204);
    }

    /**
     * Correcting a lot's opening balance (amount or reference date) shouldn't
     * touch regular monthly fund calls, so this only accepts calls flagged
     * as such.
     */
    public function updateOpeningBalance(UpdateOpeningBalanceRequest $request, FundCall $fundCall): JsonResponse
    {
        abort_unless($fundCall->is_opening_balance, 404);

        $fundCall->update($request->validated());

        return response()->json(['data' => $fundCall->fresh(['lot', 'payments'])]);
    }
}
