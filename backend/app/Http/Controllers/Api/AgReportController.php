<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures an assemblée générale votes on, for one exercise.
 *
 * Unlike the treasury report — which follows the cash and must reconcile
 * against the bank — cotisations here are counted against the month they
 * cover, not the month the money arrived. A resident settling next year's
 * dues in December belongs to next year's exercise, and an AG needs to see
 * the year it is actually reviewing.
 *
 * Revenues and expenses carry a single date each (there is no "period
 * covered" for them), so they are grouped by that date.
 */
class AgReportController extends Controller
{
    /**
     * @param  Collection<int, object>  $records
     * @return array<int, float> 12-slot array (index 0 = January)
     */
    private function amountsByMonth($records, string $dateColumn): array
    {
        $amounts = array_fill(0, 12, 0);

        foreach ($records as $record) {
            $amounts[$record->{$dateColumn}->month - 1] += $record->amount;
        }

        return $amounts;
    }

    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;

        $cotisations = array_fill(0, 12, 0);

        Payment::with('fundCall')
            ->whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', false)->whereYear('period', $year))
            ->get()
            ->each(function (Payment $payment) use (&$cotisations) {
                $cotisations[$payment->fundCall->period->month - 1] += $payment->amount;
            });

        // Repayments of pre-platform debt: real money, but it settles older
        // exercises, so it is reported on its own line rather than mixed
        // into the year's cotisations.
        $openingBalanceRecovered = $this->amountsByMonth(
            Payment::whereYear('paid_at', $year)
                ->whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', true))
                ->get(['amount', 'paid_at']),
            'paid_at',
        );

        $revenueCategories = RevenueCategory::orderBy('name')->get()
            ->map(function (RevenueCategory $category) use ($year) {
                $amounts = $this->amountsByMonth(
                    Revenue::where('revenue_category_id', $category->id)->whereYear('received_at', $year)->get(['amount', 'received_at']),
                    'received_at',
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $expenseCategories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get()
            ->map(function (ExpenseCategory $category) use ($year) {
                $amounts = $this->amountsByMonth(
                    Expense::where('expense_category_id', $category->id)->whereYear('paid_at', $year)->get(['amount', 'paid_at']),
                    'paid_at',
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $incomeByMonth = $cotisations;

        foreach ($openingBalanceRecovered as $index => $amount) {
            $incomeByMonth[$index] += $amount;
        }

        foreach ($revenueCategories as $category) {
            foreach ($category['amounts'] as $index => $amount) {
                $incomeByMonth[$index] += $amount;
            }
        }

        $expensesByMonth = array_fill(0, 12, 0);

        foreach ($expenseCategories as $category) {
            foreach ($category['amounts'] as $index => $amount) {
                $expensesByMonth[$index] += $amount;
            }
        }

        $netByMonth = [];

        for ($i = 0; $i < 12; $i++) {
            $netByMonth[] = $incomeByMonth[$i] - $expensesByMonth[$i];
        }

        $residence = $request->user()->residence;
        $totalIncome = array_sum($incomeByMonth);
        $totalExpenses = array_sum($expensesByMonth);
        $result = $totalIncome - $totalExpenses;

        // Cash actually held at each end of the year, so the AG can tie its
        // exercise back to the bank. Opening equals the previous year's
        // closing by construction (same shared method as Trésorerie).
        $openingBalance = $residence->cashBalanceBefore(Carbon::create($year, 1, 1)->startOfDay());
        $cashClosingBalance = $residence->cashBalanceBefore(Carbon::create($year + 1, 1, 1)->startOfDay());

        // Opening + result only lands on the real cash balance when every
        // cotisation was cashed in the year it covers. Dues paid early or
        // late make up the rest, and hiding that gap would leave the
        // treasurer unable to answer "why doesn't this match the bank?".
        $timingDifference = $openingBalance + $result - $cashClosingBalance;

        return response()->json([
            'year' => $year,
            'residence_name' => $residence->name,
            'cotisations' => $cotisations,
            'opening_balance_recovered' => $openingBalanceRecovered,
            'revenue_categories' => $revenueCategories,
            'expense_categories' => $expenseCategories,
            'income_by_month' => $incomeByMonth,
            'expenses_by_month' => $expensesByMonth,
            'net_by_month' => $netByMonth,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'result' => $result,
            'opening_balance' => $openingBalance,
            'cash_closing_balance' => $cashClosingBalance,
            'timing_difference' => $timingDifference,
        ]);
    }
}
