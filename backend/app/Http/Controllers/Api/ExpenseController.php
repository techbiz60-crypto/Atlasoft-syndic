<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('category')->orderByDesc('paid_at');

        if ($request->filled('year')) {
            $query->whereYear('paid_at', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('paid_at', $request->integer('month'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->safe()->except('receipt');

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts');
        }

        $expense = Expense::create($data);

        return response()->json(['data' => $expense->load('category')], 201);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $data = $request->safe()->except('receipt');

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('receipts');
        }

        $expense->update($data);

        return response()->json(['data' => $expense->load('category')]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if ($expense->receipt_path) {
            Storage::delete($expense->receipt_path);
        }

        $expense->delete();

        return response()->json(status: 204);
    }

    public function receipt(Expense $expense): Response
    {
        abort_unless($expense->receipt_path && Storage::exists($expense->receipt_path), 404);

        return Storage::response($expense->receipt_path);
    }
}
