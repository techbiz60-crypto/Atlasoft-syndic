<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LotTypeRate\StoreLotTypeRateRequest;
use App\Models\LotType;
use App\Models\LotTypeRate;
use Illuminate\Http\JsonResponse;

class LotTypeRateController extends Controller
{
    public function store(StoreLotTypeRateRequest $request, LotType $lotType): JsonResponse
    {
        $rate = $lotType->rates()->create($request->validated());

        return response()->json(['data' => $rate, 'lot_type' => $lotType->fresh('rates')], 201);
    }

    public function destroy(LotType $lotType, LotTypeRate $rate): JsonResponse
    {
        abort_unless($rate->lot_type_id === $lotType->id, 404);
        abort_if($lotType->rates()->count() <= 1, 422, 'Un type de lot doit garder au moins un montant.');

        $rate->delete();

        return response()->json(status: 204);
    }
}
