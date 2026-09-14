<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralAssembly\UpdateGeneralAssemblyRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            ->get(['exercise_year', 'held_on', 'location', 'meeting_time', 'agenda', 'convocation_sent_at']);

        return response()->json(['data' => $assemblies]);
    }

    public function update(UpdateGeneralAssemblyRequest $request, int $year): JsonResponse
    {
        $assembly = $request->user()->residence->generalAssemblies()
            ->updateOrCreate(['exercise_year' => $year], $request->validated());

        return response()->json(['data' => $assembly]);
    }

    public function destroy(Request $request, int $year): JsonResponse
    {
        $request->user()->residence->generalAssemblies()->where('exercise_year', $year)->delete();

        return response()->json(status: 204);
    }

    /**
     * The convocation letter itself — lieu, date, heure et ordre du jour,
     * plus the reminder Loi 18-00 (art. 16 mukarrar 4) requires: that
     * unpaid charges bar attendance. Printing this doesn't record that it
     * was actually sent — convocation_sent_at is filled in by hand, since
     * the app has no way to know when a physical letter went out.
     */
    public function convocation(Request $request, int $year): Response
    {
        $residence = $request->user()->residence;
        $assembly = $residence->generalAssemblies()->where('exercise_year', $year)->firstOrFail();

        $pdf = Pdf::loadView('convocations.general-assembly', [
            'residence' => $residence,
            'assembly' => $assembly,
            'year' => $year,
        ]);

        return $pdf->stream("convocation-ag-{$year}.pdf");
    }
}
