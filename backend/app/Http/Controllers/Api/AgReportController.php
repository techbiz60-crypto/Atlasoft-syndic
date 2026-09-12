<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Residence;
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
    /**
     * What every exercise before this one left behind.
     *
     * A due still belongs to the exercise matching its own period — a 2026
     * due is always 2026's, whether it's settled early or late — so that
     * part of the filter stays purely calendar-based. But the cash itself
     * only counts as "settled" here once it was actually paid before this
     * exercise's own cash window opened (the AG that closed exercise
     * $year - 1); a late payment that arrives after that point belongs to
     * whichever exercise is open when it's actually paid, so it must not be
     * double-counted here as well as on that later exercise's own report.
     */
    private function balanceBeforeYear(Residence $residence, int $year): float
    {
        $yearStart = $residence->fiscalYearStartsOn($year);
        $cutoff = $residence->agCutoffFor($year - 1);

        $cotisations = Payment::whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', false)->whereDate('period', '<', $yearStart))
            ->whereDate('paid_at', '<', $cutoff)
            ->sum('amount');

        $openingBalanceRecovered = Payment::whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', true))
            ->whereDate('paid_at', '<', $cutoff)
            ->sum('amount');

        $revenues = Revenue::whereDate('received_at', '<', $cutoff)->sum('amount');
        $expenses = Expense::whereDate('paid_at', '<', $cutoff)->sum('amount');

        return $residence->opening_balance + $cotisations + $openingBalanceRecovered + $revenues - $expenses;
    }

    private function amountsByMonth($records, string $dateColumn, Carbon $fiscalStart): array
    {
        $amounts = array_fill(0, 12, 0);

        foreach ($records as $record) {
            $amounts[$this->monthIndex($record->{$dateColumn}, $fiscalStart)] += $record->amount;
        }

        return $amounts;
    }

    /**
     * Position (0-11) of $date among the exercise's 12 consecutive months,
     * counting from $fiscalStart rather than assuming January.
     */
    private function monthIndex(Carbon $date, Carbon $fiscalStart): int
    {
        return ($date->year - $fiscalStart->year) * 12 + ($date->month - $fiscalStart->month);
    }

    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;
        $residence = $request->user()->residence;

        $yearStart = $residence->fiscalYearStartsOn($year);
        $yearEnd = $residence->fiscalYearEndsOn($year);
        // The cash window this exercise's own report can still recognise
        // money in: from the moment the previous exercise's AG closed its
        // books, until this exercise's own AG closes these. Falls back to
        // the exercise's own end date when no AG date has been recorded, so
        // a residence with no AG dates on file behaves exactly as before.
        $cutoffStart = $residence->agCutoffFor($year - 1);
        $cutoffEnd = $residence->agCutoffFor($year);

        $cotisations = array_fill(0, 12, 0);

        // A due belongs to the exercise whose window contains its own
        // period — not the exercise matching its calendar year, since a
        // custom fiscal year (e.g. starting mid-month) can straddle two —
        // but only up to the moment this exercise's AG closes the books.
        // Paid any later, it no longer counts as this exercise's own
        // cotisation; it's recovered debt for whichever exercise is open
        // when it's actually paid (see below).
        Payment::with('fundCall')
            ->whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', false)
                ->whereDate('period', '>=', $yearStart)->whereDate('period', '<', $yearEnd))
            ->whereDate('paid_at', '<', $cutoffEnd)
            ->get()
            ->each(function (Payment $payment) use (&$cotisations, $yearStart) {
                $cotisations[$this->monthIndex($payment->fundCall->period, $yearStart)] += $payment->amount;
            });

        // Money that settles a debt from an exercise whose books are
        // already closed — the pre-platform opening balance, but just as
        // much an ordinary cotisation from a prior calendar year, paid
        // after its own exercise's AG already happened (e.g. December 2026
        // settled in 2027, once the new conseil is already in office).
        // Real income for this exercise, but it must never be mixed into
        // "cotisations de l'exercice" above, which only covers this year's
        // own months — so it gets its own line instead.
        $priorDebtRecovered = $this->amountsByMonth(
            Payment::whereDate('paid_at', '>=', $cutoffStart)->whereDate('paid_at', '<', $cutoffEnd)
                ->whereHas('fundCall', fn ($query) => $query->where('is_opening_balance', true)
                    ->orWhere(fn ($q) => $q->where('is_opening_balance', false)->whereDate('period', '<', $yearStart)))
                ->get(['amount', 'paid_at']),
            'paid_at',
            $yearStart,
        );

        $revenueCategories = RevenueCategory::orderBy('name')->get()
            ->map(function (RevenueCategory $category) use ($cutoffStart, $cutoffEnd, $yearStart) {
                $amounts = $this->amountsByMonth(
                    Revenue::where('revenue_category_id', $category->id)
                        ->whereDate('received_at', '>=', $cutoffStart)->whereDate('received_at', '<', $cutoffEnd)
                        ->get(['amount', 'received_at']),
                    'received_at',
                    $yearStart,
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $expenseCategories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get()
            ->map(function (ExpenseCategory $category) use ($cutoffStart, $cutoffEnd, $yearStart) {
                $amounts = $this->amountsByMonth(
                    Expense::where('expense_category_id', $category->id)
                        ->whereDate('paid_at', '>=', $cutoffStart)->whereDate('paid_at', '<', $cutoffEnd)
                        ->get(['amount', 'paid_at']),
                    'paid_at',
                    $yearStart,
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $incomeByMonth = $cotisations;

        foreach ($priorDebtRecovered as $index => $amount) {
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

        $totalIncome = array_sum($incomeByMonth);
        $totalExpenses = array_sum($expensesByMonth);
        $result = $totalIncome - $totalExpenses;

        // Everything here stays on the exercise basis, opening balance
        // included — so opening + result always equals closing, with no gap
        // to explain. The cash position that reconciles with the bank is
        // Trésorerie's job, not this report's.
        $openingBalance = $this->balanceBeforeYear($residence, $year);

        // The 12 columns no longer necessarily line up with January-December
        // — a custom fiscal year shifts them — so the frontend renders each
        // one from its actual "Y-m" instead of a fixed month-name list.
        $monthPeriods = [];
        for ($i = 0; $i < 12; $i++) {
            $monthPeriods[] = $yearStart->copy()->addMonthsNoOverflow($i)->format('Y-m');
        }

        return response()->json([
            'year' => $year,
            'residence_name' => $residence->name,
            'month_periods' => $monthPeriods,
            'cotisations' => $cotisations,
            'prior_debt_recovered' => $priorDebtRecovered,
            'revenue_categories' => $revenueCategories,
            'expense_categories' => $expenseCategories,
            'income_by_month' => $incomeByMonth,
            'expenses_by_month' => $expensesByMonth,
            'net_by_month' => $netByMonth,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'result' => $result,
            'opening_balance' => $openingBalance,
            'closing_balance' => $openingBalance + $result,
        ]);
    }
}
