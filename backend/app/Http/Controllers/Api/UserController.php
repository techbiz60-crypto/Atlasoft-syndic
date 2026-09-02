<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => User::where('residence_id', $request->user()->residence_id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'whatsapp_number']),
        ]);
    }

    /**
     * Generates the account with a random password and returns it once in
     * the response — there's no email-invite flow yet, so the admin is
     * expected to copy it and hand it to the person directly.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $generatedPassword = Str::password(12);

        $user = User::create([
            ...$request->validated(),
            'residence_id' => $request->user()->residence_id,
            'password' => Hash::make($generatedPassword),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'data' => $user,
            'generated_password' => $generatedPassword,
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->residence_id === $request->user()->residence_id, 404);
        abort_if($user->isAdmin(), 422, 'Impossible de supprimer le compte administrateur.');
        abort_if($user->id === $request->user()->id, 422, 'Impossible de supprimer votre propre compte.');

        $user->delete();

        return response()->json(status: 204);
    }
}
