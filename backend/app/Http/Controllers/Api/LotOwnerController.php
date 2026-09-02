<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lot\StoreLotOwnerRequest;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LotOwnerController extends Controller
{
    public function index(Lot $lot): JsonResponse
    {
        return response()->json(['data' => $lot->owners()->get()]);
    }

    /**
     * Records a change of ownership: adds a new entry to the lot's history
     * and updates the lot's current owner_* fields to match — everything
     * else (payments, debt, fund calls) stays attached to the lot, exactly
     * as before.
     */
    public function store(StoreLotOwnerRequest $request, Lot $lot): JsonResponse
    {
        $owner = DB::transaction(function () use ($request, $lot) {
            $owner = $lot->owners()->create($request->validated());

            $lot->update([
                'owner_name' => $request->input('owner_name'),
                'owner_phone' => $request->input('owner_phone'),
                'owner_email' => $request->input('owner_email'),
            ]);

            return $owner;
        });

        return response()->json(['data' => $owner], 201);
    }
}
