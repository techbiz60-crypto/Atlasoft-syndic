<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lot\BulkStoreLotsRequest;
use App\Http\Requests\Lot\StoreLotRequest;
use App\Http\Requests\Lot\UpdateLotRequest;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LotController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Lot::with(['lotType', 'building', 'openingBalance', 'residentUser'])->orderBy('number')->get()]);
    }

    public function store(StoreLotRequest $request): JsonResponse
    {
        $subscription = $request->user()->residence->subscription;
        $maxLots = $subscription?->plan->maxLots();

        if ($maxLots !== null && Lot::count() >= $maxLots) {
            abort(422, "Votre pack actuel ({$subscription->plan_label}, jusqu'à {$maxLots} appartements) est atteint. Passez à un pack supérieur pour ajouter plus d'appartements.");
        }

        $lot = Lot::create($request->validated());

        return response()->json(['data' => $lot->load(['lotType', 'building', 'openingBalance', 'residentUser'])], 201);
    }

    /**
     * Lets a syndic starting on the platform import all of a building's
     * apartments and owners at once (typically pasted from an existing
     * Excel sheet) instead of adding each lot one by one.
     */
    public function bulkStore(BulkStoreLotsRequest $request): JsonResponse
    {
        $rows = $request->input('lots');
        $subscription = $request->user()->residence->subscription;
        $maxLots = $subscription?->plan->maxLots();

        if ($maxLots !== null && Lot::count() + count($rows) > $maxLots) {
            $newRowsCount = count($rows);
            abort(422, "Votre pack actuel ({$subscription->plan_label}, jusqu'à {$maxLots} appartements) ne permet pas d'ajouter {$newRowsCount} lots supplémentaires. Passez à un pack supérieur.");
        }

        $lots = DB::transaction(function () use ($request, $rows) {
            $buildingId = $request->integer('building_id');

            return Collection::make($rows)->map(fn (array $row) => Lot::create([
                'building_id' => $buildingId,
                'lot_type_id' => $row['lot_type_id'],
                'number' => $row['number'],
                'floor' => $row['floor'] ?? null,
                'owner_name' => $row['owner_name'],
                'owner_phone' => $row['owner_phone'] ?? null,
                'owner_email' => $row['owner_email'] ?? null,
            ]));
        });

        $lots->load(['lotType', 'building', 'openingBalance', 'residentUser']);

        return response()->json([
            'data' => $lots,
            'created' => $lots->count(),
        ], 201);
    }

    public function update(UpdateLotRequest $request, Lot $lot): JsonResponse
    {
        $lot->update($request->validated());

        return response()->json(['data' => $lot->load(['lotType', 'building', 'openingBalance', 'residentUser'])]);
    }

    public function destroy(Lot $lot): JsonResponse
    {
        $lot->delete();

        return response()->json(status: 204);
    }
}
