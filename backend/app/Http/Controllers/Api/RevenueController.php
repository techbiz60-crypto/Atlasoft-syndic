<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Revenue\StoreRevenueRequest;
use App\Models\Revenue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class RevenueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Revenue::with('category')->orderByDesc('received_at');

        if ($request->filled('year')) {
            $query->whereYear('received_at', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('received_at', $request->integer('month'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreRevenueRequest $request): JsonResponse
    {
        $data = $request->safe()->except('receipt');

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts');
        }

        $revenue = Revenue::create($data);

        return response()->json(['data' => $revenue->load('category')], 201);
    }

    public function destroy(Revenue $revenue): JsonResponse
    {
        if ($revenue->receipt_path) {
            Storage::delete($revenue->receipt_path);
        }

        $revenue->delete();

        return response()->json(status: 204);
    }

    public function receipt(Revenue $revenue): Response
    {
        abort_unless($revenue->receipt_path && Storage::exists($revenue->receipt_path), 404);

        return Storage::response($revenue->receipt_path);
    }
}
