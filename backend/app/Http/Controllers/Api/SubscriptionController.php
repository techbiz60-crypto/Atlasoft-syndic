<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->residence->subscription]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = $request->user()->residence->subscription
            ?->invoices()
            ->orderByDesc('validated_at')
            ->get() ?? [];

        return response()->json(['data' => $invoices]);
    }
}
