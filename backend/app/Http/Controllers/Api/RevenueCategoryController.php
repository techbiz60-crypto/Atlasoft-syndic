<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevenueCategory\StoreRevenueCategoryRequest;
use App\Http\Requests\RevenueCategory\UpdateRevenueCategoryRequest;
use App\Models\RevenueCategory;
use Illuminate\Http\JsonResponse;

class RevenueCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => RevenueCategory::withCount('revenues')->orderBy('name')->get()]);
    }

    public function store(StoreRevenueCategoryRequest $request): JsonResponse
    {
        $category = RevenueCategory::create($request->validated());

        return response()->json(['data' => $category], 201);
    }

    public function update(UpdateRevenueCategoryRequest $request, RevenueCategory $revenueCategory): JsonResponse
    {
        $revenueCategory->update($request->validated());

        return response()->json(['data' => $revenueCategory]);
    }

    public function destroy(RevenueCategory $revenueCategory): JsonResponse
    {
        abort_if($revenueCategory->revenues()->exists(), 422, 'Impossible de supprimer une catégorie déjà utilisée par des recettes.');

        $revenueCategory->delete();

        return response()->json(status: 204);
    }
}
