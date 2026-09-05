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
        $lots = Lot::with(['building', 'lotType.rates', 'fundCalls.payments'])->get();
        $currentMonth = Carbon::now()->startOfMonth();

        $unpaid = $lots
            ->map(fn (Lot $lot) => $this->unpaidSummaryFor($lot, $currentMonth))
            ->filter()
            ->sortByDesc('months_late')
            ->values();

        return response()->json(['data' => $unpaid]);
    }

    /**
     * A lot's debt is its unpaid opening balance (which stands for everything
     * owed before the current year) plus every month of the current year up
     * to and including the current one — whether that month was actually
     * billed or not, since fund calls are only created lazily and a month
     * with no row is still owed.
     *
     * @return array<string, mixed>|null
     */
    private function unpaidSummaryFor(Lot $lot, Carbon $currentMonth): ?array
    {
        $openingBalanceCall = $lot->fundCalls->first(fn (FundCall $call) => $call->is_opening_balance);
        $openingBalanceDue = $openingBalanceCall && $openingBalanceCall->status !== 'paid'
            ? $openingBalanceCall->amount - $openingBalanceCall->paid_amount
            : 0;

        $callsByMonth = $lot->fundCalls
            ->reject(fn (FundCall $call) => $call->is_opening_balance)
            ->keyBy(fn (FundCall $call) => $call->period->format('Y-m'));

        $unpaidMonths = collect();
        $monthlyDue = 0;

        // Any month already billed and still unpaid counts, whatever its year.
        foreach ($callsByMonth as $call) {
            if ($call->period->lte($currentMonth) && $call->status !== 'paid') {
                $monthlyDue += $call->amount - $call->paid_amount;
                $unpaidMonths->push($call->period->copy());
            }
        }

        // Months of the current year that were never billed at all, projected
        // at the lot's rate. Starts after the opening balance's reference
        // month when that falls inside the year, so the lump sum and the
        // monthly projection never overlap.
        $projectionStart = $openingBalanceCall
            ? $openingBalanceCall->period->copy()->addMonthNoOverflow()->startOfMonth()->max($currentMonth->copy()->startOfYear())
            : $currentMonth->copy()->startOfYear();

        for ($cursor = $projectionStart->copy(); $cursor->lte($currentMonth); $cursor->addMonthNoOverflow()) {
            if ($callsByMonth->has($cursor->format('Y-m'))) {
                continue;
            }

            $rate = $lot->lotType->rateAt($cursor)?->amount;

            if ($rate > 0) {
                $monthlyDue += $rate;
                $unpaidMonths->push($cursor->copy());
            }
        }

        $totalDue = $monthlyDue + $openingBalanceDue;

        if ($totalDue <= 0) {
            return null;
        }

        $lastPayment = $lot->fundCalls
            ->flatMap(fn (FundCall $call) => $call->payments)
            ->sortByDesc('paid_at')
            ->first();

        // "Months late" is the WHOLE debt (monthly arrears + opening balance
        // combined) converted to an equivalent number of months at the lot's
        // current monthly rate — one unified figure.
        $monthlyRate = $lot->lotType->rateAt($currentMonth)?->amount;
        $monthsLate = $monthlyRate > 0
            ? (int) ceil($totalDue / $monthlyRate)
            : $unpaidMonths->count() + ($openingBalanceDue > 0 ? 1 : 0);

        return [
            'lot_id' => $lot->id,
            'lot_number' => $lot->number,
            'building_name' => $lot->building->name,
            'owner_name' => $lot->owner_name,
            'owner_phone' => $lot->owner_phone,
            'total_due' => $totalDue,
            'months_late' => $monthsLate,
            'oldest_unpaid_period' => $openingBalanceDue > 0
                ? $openingBalanceCall->period->toDateString()
                : $unpaidMonths->min()?->toDateString(),
            'last_payment_date' => $lastPayment?->paid_at->toDateString(),
            'opening_balance_due' => $openingBalanceDue,
        ];
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

    /**
     * payments.fund_call_id cascades, so deleting a fund call that has been
     * paid would erase those payments too — the payment must be deleted
     * explicitly first, which is an auditable action of its own.
     */
    public function destroy(FundCall $fundCall): JsonResponse
    {
        abort_if(
            $fundCall->payments()->exists(),
            422,
            'Impossible de supprimer un appel de fonds qui a déjà reçu des paiements.'
        );

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
