<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residence\UpdateResidenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->residence]);
    }

    public function update(UpdateResidenceRequest $request): JsonResponse
    {
        $residence = $request->user()->residence;
        $residence->update($request->validated());

        return response()->json(['data' => $residence]);
    }
}
