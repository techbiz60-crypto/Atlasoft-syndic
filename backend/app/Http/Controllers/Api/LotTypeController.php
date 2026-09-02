<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LotType\StoreLotTypeRequest;
use App\Http\Requests\LotType\UpdateLotTypeRequest;
use App\Models\LotType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LotTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => LotType::with('rates')->orderBy('name')->get()]);
    }

    public function store(StoreLotTypeRequest $request): JsonResponse
    {
        $lotType = DB::transaction(function () use ($request) {
            $lotType = LotType::create(['name' => $request->string('name')]);

            $lotType->rates()->create([
                'residence_id' => $lotType->residence_id,
                'amount' => $request->integer('amount'),
                'effective_date' => $request->date('effective_date') ?? now(),
            ]);

            return $lotType;
        });

        return response()->json(['data' => $lotType->load('rates')], 201);
    }

    public function update(UpdateLotTypeRequest $request, LotType $lotType): JsonResponse
    {
        $lotType->update($request->validated());

        return response()->json(['data' => $lotType->load('rates')]);
    }

    public function destroy(LotType $lotType): JsonResponse
    {
        $lotType->delete();

        return response()->json(status: 204);
    }
}
