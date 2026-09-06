<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Revenue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The detail behind the treasury summary: every cash movement of the year
 * in date order, each carrying the running balance after it.
 *
 * Same basis as the treasury report (money in and out, on the date it
 * moved), so the last running balance of a year equals that year's closing
 * balance on the summary tab.
 */
class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: Carbon::now()->year;
        $residence = $request->user()->residence;
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();

        // Everything that happened before the year is only needed as a
        // starting point, so it is summed in SQL rather than listed.
        $balance = $residence->opening_balance
            + Payment::whereDate('paid_at', '<', $startOfYear)->sum('amount')
            + Revenue::whereDate('received_at', '<', $startOfYear)->sum('amount')
            - Expense::whereDate('paid_at', '<', $startOfYear)->sum('amount');

        $openingBalance = $balance;

        $movements = $this->movementsForYear($year)
            ->map(function (array $movement) use (&$balance) {
                $balance += $movement['direction'] === 'in' ? $movement['amount'] : -$movement['amount'];

                return [...$movement, 'balance' => $balance];
            })
            ->values();

        return response()->json([
            'year' => $year,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
            'total_in' => $movements->where('direction', 'in')->sum('amount'),
            'total_out' => $movements->where('direction', 'out')->sum('amount'),
            'data' => $movements,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function movementsForYear(int $year): Collection
    {
        // Paying several months at once creates one Payment row per month
        // under a shared batch_id. That is an internal allocation — the bank
        // saw a single movement, so the ledger shows a single line too,
        // exactly like the Paiements screen already does.
        $payments = Payment::with('fundCall.lot')
            ->whereYear('paid_at', $year)
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->batch_id ?? 'single-'.$payment->id)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'id' => 'payment-'.$first->id,
                    'date' => $first->paid_at->toDateString(),
                    'direction' => 'in',
                    'kind' => $first->fundCall->is_opening_balance ? 'opening_balance' : 'cotisation',
                    'label' => $first->owner_name ?? $first->fundCall->lot->owner_name,
                    'reference' => $first->fundCall->lot->number,
                    'method' => $first->method->value,
                    'amount' => $group->sum('amount'),
                    'months_covered' => $group->count(),
                    'sort_key' => $first->paid_at->toDateString().'-1-'.$first->id,
                ];
            })
            ->values();

        $revenues = Revenue::with('category')
            ->whereYear('received_at', $year)
            ->get()
            ->map(fn (Revenue $revenue) => [
                'id' => 'revenue-'.$revenue->id,
                'date' => $revenue->received_at->toDateString(),
                'direction' => 'in',
                'kind' => 'revenue',
                'label' => $revenue->label ?: $revenue->category->name,
                'reference' => $revenue->category->name,
                'method' => $revenue->method->value,
                'amount' => $revenue->amount,
                'sort_key' => $revenue->received_at->toDateString().'-2-'.$revenue->id,
            ]);

        $expenses = Expense::with('category')
            ->whereYear('paid_at', $year)
            ->get()
            ->map(fn (Expense $expense) => [
                'id' => 'expense-'.$expense->id,
                'date' => $expense->paid_at->toDateString(),
                'direction' => 'out',
                'kind' => 'expense',
                'label' => $expense->label ?: $expense->category->name,
                'reference' => $expense->category->name,
                'method' => $expense->method->value,
                'amount' => $expense->amount,
                'sort_key' => $expense->paid_at->toDateString().'-3-'.$expense->id,
            ]);

        return $payments
            ->concat($revenues)
            ->concat($expenses)
            ->sortBy('sort_key')
            ->map(fn (array $movement) => collect($movement)->except('sort_key')->all());
    }
}
