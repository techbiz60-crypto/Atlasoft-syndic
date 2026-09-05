<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Models\Building;
use Illuminate\Http\JsonResponse;

class BuildingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Building::withCount('lots')->orderBy('name')->get()]);
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = Building::create($request->validated());

        return response()->json(['data' => $building], 201);
    }

    public function update(UpdateBuildingRequest $request, Building $building): JsonResponse
    {
        $building->update($request->validated());

        return response()->json(['data' => $building]);
    }

    /**
     * The foreign keys cascade (building → lots → fund calls → payments), so
     * deleting a building that still has apartments would silently wipe out
     * their whole billing and payment history.
     */
    public function destroy(Building $building): JsonResponse
    {
        abort_if(
            $building->lots()->exists(),
            422,
            'Impossible de supprimer un immeuble qui contient encore des appartements.'
        );

        $building->delete();

        return response()->json(status: 204);
    }
}
