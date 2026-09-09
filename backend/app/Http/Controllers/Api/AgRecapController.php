<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Lot;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The per-building recap an assemblée générale reads: how many apartments
 * each block holds, how much it owed for the exercise, how much of that
 * came in, and how much of the previous years' arrears was recovered along
 * the way.
 *
 * Cotisations are counted against the months they cover, like the rest of
 * the AG reporting — dues for the exercise stay in the exercise whatever
 * year the money actually arrived.
 */
class AgRecapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;
        $residence = $request->user()->residence;
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        // Same exercise boundary as the AG report — the AG date that closed
        // the previous exercise, or January 1st by default — so a building's
        // recap here can never disagree with the totals on that screen.
        $cutoffStart = $residence->agCutoffFor($year - 1);
        $cutoffEnd = $residence->agCutoffFor($year);

        $lots = Lot::with('lotType.rates')->get();

        // One pass over the year's payments, then everything is read from
        // memory — the alternative is a handful of queries per lot.
        $paidForYearByLot = Payment::whereHas(
            'fundCall',
            fn ($query) => $query->where('is_opening_balance', false)->whereYear('period', $year)
        )
            ->whereDate('paid_at', '<', $cutoffEnd)
            ->with('fundCall:id,lot_id')
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->fundCall->lot_id)
            ->map(fn ($payments) => $payments->sum('amount'));

        // Cash that came in during the year but settles something older:
        // earlier exercises' months, plus opening-balance (pre-platform) debt.
        $paidForArrearsByLot = Payment::whereDate('paid_at', '>=', $cutoffStart)->whereDate('paid_at', '<', $cutoffEnd)
            ->whereHas(
                'fundCall',
                fn ($query) => $query->where('is_opening_balance', true)
                    ->orWhere(fn ($q) => $q->where('is_opening_balance', false)->whereDate('period', '<', $startOfYear))
            )
            ->with('fundCall:id,lot_id')
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->fundCall->lot_id)
            ->map(fn ($payments) => $payments->sum('amount'));

        $buildings = Building::orderBy('name')->get()
            ->map(function (Building $building) use ($lots, $paidForYearByLot, $paidForArrearsByLot, $year) {
                $buildingLots = $lots->where('building_id', $building->id);

                $duesTotal = 0;
                $collectedForYear = 0;
                $collectedForArrears = 0;
                $payingLotsCount = 0;

                foreach ($buildingLots as $lot) {
                    // What the lot owed for the year, month by month, so a
                    // mid-year rate change is reflected rather than assuming
                    // twelve times today's amount.
                    for ($month = 1; $month <= 12; $month++) {
                        $duesTotal += $lot->lotType->rateAt(Carbon::create($year, $month, 1))?->amount ?? 0;
                    }

                    $paid = $paidForYearByLot->get($lot->id, 0);
                    $collectedForYear += $paid;
                    $collectedForArrears += $paidForArrearsByLot->get($lot->id, 0);

                    // Apartments that contributed something towards the
                    // exercise. Read against the block's own size, so it
                    // answers "how many of my apartments are paying?" without
                    // depending on any threshold.
                    if ($paid > 0) {
                        $payingLotsCount++;
                    }
                }

                $lotsCount = $buildingLots->count();

                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'lots_count' => $lotsCount,
                    'paying_lots_count' => $payingLotsCount,
                    'paying_lots_rate' => $lotsCount > 0 ? $payingLotsCount / $lotsCount : 0,
                    'dues_total' => $duesTotal,
                    'collected_for_year' => $collectedForYear,
                    'collection_rate' => $duesTotal > 0 ? $collectedForYear / $duesTotal : 0,
                    'collected_for_arrears' => $collectedForArrears,
                    'collected_total' => $collectedForYear + $collectedForArrears,
                ];
            })
            ->values();

        return response()->json([
            'year' => $year,
            'residence_name' => $request->user()->residence->name,
            'buildings' => $buildings,
            'total' => $this->totalsFor($buildings),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $buildings
     * @return array<string, mixed>
     */
    private function totalsFor($buildings): array
    {
        $lotsCount = $buildings->sum('lots_count');
        $payingLotsCount = $buildings->sum('paying_lots_count');
        $duesTotal = $buildings->sum('dues_total');
        $collectedForYear = $buildings->sum('collected_for_year');
        $collectedForArrears = $buildings->sum('collected_for_arrears');

        return [
            'lots_count' => $lotsCount,
            'paying_lots_count' => $payingLotsCount,
            // Recomputed from the totals, not averaged across blocks: a
            // 4-lot block must not weigh as much as a 37-lot one.
            'paying_lots_rate' => $lotsCount > 0 ? $payingLotsCount / $lotsCount : 0,
            'dues_total' => $duesTotal,
            'collected_for_year' => $collectedForYear,
            'collection_rate' => $duesTotal > 0 ? $collectedForYear / $duesTotal : 0,
            'collected_for_arrears' => $collectedForArrears,
            'collected_total' => $collectedForYear + $collectedForArrears,
        ];
    }
}
