<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Residence;
use App\Models\Revenue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates, in one call, everything the dashboard shows: the current cash
 * position, arrears, a collection rate, and a monthly cash-flow series —
 * instead of the page downloading every fund call, expense and revenue the
 * residence has ever had and adding them up in the browser.
 *
 * Two different bases are deliberately in play here, matching the rest of
 * the app: cash amounts (encaissé/dépenses/recettes, the chart) follow the
 * date money actually moved, like Trésorerie and the Grand livre, and must
 * stay reconcilable with the bank. The collection rate follows the month a
 * cotisation covers, like the AG report, since "what fraction of what was
 * owed came in" only makes sense against the months actually due.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $residence = $request->user()->residence;
        $buildingId = $request->integer('building_id') ?: null;

        $to = $request->filled('to') ? Carbon::parse($request->string('to'))->startOfDay() : Carbon::now()->startOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : Carbon::create($to->year, 1, 1);

        $lots = Lot::with(['building', 'lotType.rates', 'fundCalls.payments'])
            ->when($buildingId, fn ($query) => $query->where('building_id', $buildingId))
            ->get();

        $currentMonth = Carbon::now()->startOfMonth();
        $fundCallController = new FundCallController;

        $unpaid = $lots
            ->map(fn (Lot $lot) => $fundCallController->unpaidSummaryFor($lot, $currentMonth))
            ->filter()
            ->sortByDesc('total_due')
            ->values();

        [$duesTotal, $collectedForRange] = $this->collectionForRange($lots, $from, $to);

        // Revenues and expenses aren't tied to a lot or a building, so a
        // building filter narrows the cotisation figures but leaves these
        // as the whole residence's — there is nothing to filter them by.
        $payments = Payment::with('fundCall.lot')
            ->whereBetween('paid_at', [$from, $to])
            ->when($buildingId, fn ($query) => $query->whereHas('fundCall.lot', fn ($q) => $q->where('building_id', $buildingId)))
            ->get();
        $revenues = Revenue::with('category')->whereBetween('received_at', [$from, $to])->get();
        $expenses = Expense::with('category')->whereBetween('paid_at', [$from, $to])->get();

        $collectedTotal = $payments->sum('amount');
        $revenuesTotal = $revenues->sum('amount');
        $expensesTotal = $expenses->sum('amount');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'building_id' => $buildingId,
            'cash_balance' => $residence->cashBalanceBefore($to->copy()->addDay()),
            'unpaid_total' => $unpaid->sum('total_due'),
            'unpaid_count' => $unpaid->count(),
            'top_unpaid' => $unpaid->take(10)->values(),
            'collected_total' => $collectedTotal,
            'revenues_total' => $revenuesTotal,
            'expenses_total' => $expensesTotal,
            'dues_total' => $duesTotal,
            'collected_for_range' => $collectedForRange,
            'collection_rate' => $duesTotal > 0 ? $collectedForRange / $duesTotal : 0,
            'monthly_series' => $this->monthlySeries($residence, $from, $to, $buildingId),
            'recent_movements' => $this->recentMovements($payments, $revenues, $expenses, $buildingId),
        ]);
    }

    /**
     * Dues and collections for the whole months touched by [$from, $to],
     * counted the same way the AG report counts an exercise: against the
     * month a cotisation covers, not the date it was paid.
     *
     * @param  Collection<int, Lot>  $lots
     * @return array{0: float, 1: float}
     */
    private function collectionForRange(Collection $lots, Carbon $from, Carbon $to): array
    {
        $rangeStart = $from->copy()->startOfMonth();
        $rangeEnd = $to->copy()->startOfMonth();

        $duesTotal = 0;
        $collected = 0;

        foreach ($lots as $lot) {
            for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addMonthNoOverflow()) {
                $duesTotal += $lot->lotType->rateAt($cursor)?->amount ?? 0;
            }

            $collected += $lot->fundCalls
                ->reject(fn ($call) => $call->is_opening_balance)
                ->filter(fn ($call) => $call->period->between($rangeStart, $rangeEnd))
                ->sum('paid_amount');
        }

        return [$duesTotal, $collected];
    }

    /**
     * @return array<int, array{month: string, income: float, expenses: float, net: float, balance: float}>
     */
    private function monthlySeries(Residence $residence, Carbon $from, Carbon $to, ?int $buildingId): array
    {
        $rangeStart = $from->copy()->startOfMonth();
        $rangeEnd = $to->copy()->startOfMonth();

        $balance = $residence->cashBalanceBefore($rangeStart);

        $series = [];

        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addMonthNoOverflow()) {
            $monthStart = $cursor->copy();
            $monthEnd = $cursor->copy()->endOfMonth();

            $income = Payment::whereBetween('paid_at', [$monthStart, $monthEnd])
                ->when($buildingId, fn ($query) => $query->whereHas('fundCall.lot', fn ($q) => $q->where('building_id', $buildingId)))
                ->sum('amount')
                + Revenue::whereBetween('received_at', [$monthStart, $monthEnd])->sum('amount');

            $expenses = Expense::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');
            $net = $income - $expenses;
            $balance += $net;

            $series[] = [
                'month' => $monthStart->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $net,
                'balance' => $balance,
            ];
        }

        return $series;
    }

    /**
     * The ten most recent movements in range, most recent first — the same
     * shape as the Grand livre's rows, without the running balance (that
     * only means something over the full chronological history, not an
     * arbitrary window).
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentMovements(Collection $payments, Collection $revenues, Collection $expenses, ?int $buildingId): array
    {
        $paymentLines = $payments
            ->groupBy(fn (Payment $payment) => $payment->batch_id ?? 'single-'.$payment->id)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'date' => $first->paid_at->toDateString(),
                    'direction' => 'in',
                    'kind' => $first->fundCall->is_opening_balance ? 'opening_balance' : 'cotisation',
                    'label' => $first->owner_name ?? $first->fundCall->lot->owner_name,
                    'reference' => $first->fundCall->lot->number,
                    'amount' => $group->sum('amount'),
                    'sort_key' => $first->paid_at->toDateString().'-1-'.$first->id,
                ];
            })
            ->values();

        // Revenues/expenses have no building of their own, so they only
        // belong in this list when looking at the whole residence.
        $revenueLines = $buildingId ? collect() : $revenues->map(fn (Revenue $revenue) => [
            'date' => $revenue->received_at->toDateString(),
            'direction' => 'in',
            'kind' => 'revenue',
            'label' => $revenue->label ?: $revenue->category->name,
            'reference' => $revenue->category->name,
            'amount' => $revenue->amount,
            'sort_key' => $revenue->received_at->toDateString().'-2-'.$revenue->id,
        ]);

        $expenseLines = $buildingId ? collect() : $expenses->map(fn (Expense $expense) => [
            'date' => $expense->paid_at->toDateString(),
            'direction' => 'out',
            'kind' => 'expense',
            'label' => $expense->label ?: $expense->category->name,
            'reference' => $expense->category->name,
            'amount' => $expense->amount,
            'sort_key' => $expense->paid_at->toDateString().'-3-'.$expense->id,
        ]);

        return $paymentLines
            ->concat($revenueLines)
            ->concat($expenseLines)
            ->sortByDesc('sort_key')
            ->take(10)
            ->map(fn (array $movement) => collect($movement)->except('sort_key')->all())
            ->values()
            ->all();
    }
}
