<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ExpenseCategory::withCount('expenses')->orderBy('name')->get()]);
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = ExpenseCategory::create($request->validated());

        return response()->json(['data' => $category], 201);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->update($request->validated());

        return response()->json(['data' => $expenseCategory]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        abort_if($expenseCategory->expenses()->exists(), 422, 'Impossible de supprimer une catégorie déjà utilisée par des dépenses.');

        $expenseCategory->delete();

        return response()->json(status: 204);
    }
}
