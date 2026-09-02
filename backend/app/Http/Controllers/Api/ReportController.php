<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\FundCall;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * A printable, per-building ledger: for each lot, what's still owed
     * from before this year (and how much of that was paid back during
     * it), plus which months of this year were paid in full — mirrors the
     * Excel sheet syndics were already using by hand.
     */
    public function payments(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;
        $buildingId = $request->integer('building_id') ?: null;
        $yearStart = Carbon::create($year, 1, 1);

        $lots = Lot::with(['openingBalance.payments', 'fundCalls' => function ($query) use ($year) {
            $query->whereYear('period', $year)->where('is_opening_balance', false)->with('payments');
        }])
            ->when($buildingId, fn ($query) => $query->where('building_id', $buildingId))
            // Lot number always wins — a purely numeric "10" must sort after
            // "9", not before "2" as a plain string comparison would give.
            ->orderByRaw('LENGTH(number), number')
            ->get();

        $rows = $lots->map(function (Lot $lot) use ($year, $yearStart) {
            $byMonth = $lot->fundCalls->keyBy(fn (FundCall $call) => $call->period->month);

            $months = collect(range(1, 12))->map(function (int $month) use ($byMonth) {
                $call = $byMonth->get($month);

                return $call && $call->status === 'paid' ? $call->amount : null;
            });

            $openingBalanceRemaining = null;
            $openingBalancePaidThisYear = null;

            if ($lot->openingBalance) {
                $paidBeforeYear = $lot->openingBalance->payments
                    ->filter(fn ($payment) => $payment->paid_at->lt($yearStart))
                    ->sum('amount');
                $paidDuringYear = $lot->openingBalance->payments
                    ->filter(fn ($payment) => $payment->paid_at->year === $year)
                    ->sum('amount');

                $remaining = $lot->openingBalance->amount - $paidBeforeYear;
                $openingBalanceRemaining = $remaining > 0 ? -$remaining : null;
                $openingBalancePaidThisYear = $paidDuringYear > 0 ? $paidDuringYear : null;
            }

            return [
                'lot_id' => $lot->id,
                'floor' => $lot->floor,
                'lot_number' => $lot->number,
                'owner_name' => $lot->owner_name,
                'opening_balance_remaining' => $openingBalanceRemaining,
                'opening_balance_paid_this_year' => $openingBalancePaidThisYear,
                'months' => $months->values(),
                'total' => ($openingBalancePaidThisYear ?? 0) + $months->filter()->sum(),
            ];
        });

        return response()->json([
            'year' => $year,
            'building_name' => $buildingId ? Building::find($buildingId)?->name : null,
            'rows' => $rows,
        ]);
    }
}
