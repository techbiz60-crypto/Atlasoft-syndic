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

class TreasuryReportController extends Controller
{
    /**
     * @param  Collection<int, object>  $records
     * @return array<int, int> 12-slot array of summed amounts per month of the exercise, starting from $fiscalStart
     */
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

        $cotisationsByMonth = $this->amountsByMonth(
            Payment::whereDate('paid_at', '>=', $yearStart)->whereDate('paid_at', '<', $yearEnd)->get(['amount', 'paid_at']),
            'paid_at',
            $yearStart,
        );

        $revenueCategories = RevenueCategory::orderBy('name')->get()
            ->map(function (RevenueCategory $category) use ($yearStart, $yearEnd) {
                $amounts = $this->amountsByMonth(
                    Revenue::where('revenue_category_id', $category->id)
                        ->whereDate('received_at', '>=', $yearStart)->whereDate('received_at', '<', $yearEnd)
                        ->get(['amount', 'received_at']),
                    'received_at',
                    $yearStart,
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $expenseCategories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get()
            ->map(function (ExpenseCategory $category) use ($yearStart, $yearEnd) {
                $amounts = $this->amountsByMonth(
                    Expense::where('expense_category_id', $category->id)
                        ->whereDate('paid_at', '>=', $yearStart)->whereDate('paid_at', '<', $yearEnd)
                        ->get(['amount', 'paid_at']),
                    'paid_at',
                    $yearStart,
                );

                return ['name' => $category->name, 'amounts' => $amounts];
            })
            ->filter(fn ($category) => array_sum($category['amounts']) > 0)
            ->values();

        $incomeByMonth = $cotisationsByMonth;
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
        $balanceByMonth = [];

        // Cash held at the start of the exercise, not the residence's very
        // first balance — otherwise browsing a later exercise silently
        // drops everything collected in between.
        $openingBalance = $residence->cashBalanceBefore($yearStart);
        $runningBalance = $openingBalance;

        for ($i = 0; $i < 12; $i++) {
            $net = $incomeByMonth[$i] - $expensesByMonth[$i];
            $netByMonth[] = $net;
            $runningBalance += $net;
            $balanceByMonth[] = $runningBalance;
        }

        // The 12 columns no longer necessarily line up with January-December
        // — a custom fiscal year shifts them — so the frontend renders each
        // one from its actual "Y-m" instead of a fixed month-name list.
        $monthPeriods = [];
        for ($i = 0; $i < 12; $i++) {
            $monthPeriods[] = $yearStart->copy()->addMonthsNoOverflow($i)->format('Y-m');
        }

        return response()->json([
            'year' => $year,
            'month_periods' => $monthPeriods,
            'opening_balance' => $openingBalance,
            'cotisations' => $cotisationsByMonth,
            'revenue_categories' => $revenueCategories,
            'expense_categories' => $expenseCategories,
            'income_by_month' => $incomeByMonth,
            'expenses_by_month' => $expensesByMonth,
            'net_by_month' => $netByMonth,
            'balance_by_month' => $balanceByMonth,
            'closing_balance' => $runningBalance,
        ]);
    }
}
