<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralAssembly\UpdateGeneralAssemblyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The date each exercise's AG was (or is planned to be) held — the only
 * thing AgReportController reads to decide when an exercise's books
 * actually close. An exercise with no row here still works exactly as
 * before: its books close on January 1st of the following year.
 */
class GeneralAssemblyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assemblies = $request->user()->residence->generalAssemblies()
            ->orderByDesc('exercise_year')
            ->get(['exercise_year', 'held_on']);

        return response()->json(['data' => $assemblies]);
    }

    public function update(UpdateGeneralAssemblyRequest $request, int $year): JsonResponse
    {
        $assembly = $request->user()->residence->generalAssemblies()
            ->updateOrCreate(['exercise_year' => $year], ['held_on' => $request->validated('held_on')]);

        return response()->json(['data' => $assembly]);
    }

    public function destroy(Request $request, int $year): JsonResponse
    {
        $request->user()->residence->generalAssemblies()->where('exercise_year', $year)->delete();

        return response()->json(status: 204);
    }
}
