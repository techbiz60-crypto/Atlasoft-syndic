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
     * @return array<int, int> 12-slot array (index 0 = January) of summed amounts per month
     */
    private function amountsByMonth($records, string $dateColumn): array
    {
        $amounts = array_fill(0, 12, 0);

        foreach ($records as $record) {
            $month = $record->{$dateColumn}->month;
            $amounts[$month - 1] += $record->amount;
        }

        return $amounts;
    }

    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;
        $residence = $request->user()->residence;

        $cotisationsByMonth = $this->amountsByMonth(
            Payment::whereYear('paid_at', $year)->get(['amount', 'paid_at']),
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
        $runningBalance = $residence->opening_balance;

        for ($i = 0; $i < 12; $i++) {
            $net = $incomeByMonth[$i] - $expensesByMonth[$i];
            $netByMonth[] = $net;
            $runningBalance += $net;
            $balanceByMonth[] = $runningBalance;
        }

        return response()->json([
            'year' => $year,
            'opening_balance' => $residence->opening_balance,
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
