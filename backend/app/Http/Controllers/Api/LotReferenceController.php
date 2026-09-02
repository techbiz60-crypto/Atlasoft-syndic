<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lot\StoreLotReferenceRequest;
use App\Models\Lot;
use App\Models\LotReference;
use Illuminate\Http\JsonResponse;

class LotReferenceController extends Controller
{
    public function index(Lot $lot): JsonResponse
    {
        return response()->json(['data' => $lot->references]);
    }

    public function store(StoreLotReferenceRequest $request, Lot $lot): JsonResponse
    {
        $reference = $lot->references()->create($request->validated());

        return response()->json(['data' => $reference], 201);
    }

    public function destroy(LotReference $reference): JsonResponse
    {
        $reference->delete();

        return response()->json(status: 204);
    }
}
