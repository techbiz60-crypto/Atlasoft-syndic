<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lot\GrantLotAccessRequest;
use App\Models\Lot;
use App\Models\User;
use App\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LotAccessController extends Controller
{
    /**
     * Creates the resident login for this apartment's current owner —
     * name/email/phone are expected to already be pre-filled from the lot
     * on the frontend, the admin just confirms or corrects them (most lots
     * imported in bulk have no email on file yet). One account per lot.
     */
    public function store(GrantLotAccessRequest $request, Lot $lot): JsonResponse
    {
        abort_if($lot->residentUser()->exists(), 422, 'Ce propriétaire a déjà un accès.');

        $generatedPassword = Str::password(12);

        $user = User::create([
            ...$request->validated(),
            'residence_id' => $lot->residence_id,
            'lot_id' => $lot->id,
            'role' => Role::Coproprietaire,
            'password' => Hash::make($generatedPassword),
        ]);

        // The syndic vouches for the address and hands the password over
        // directly, so there is no verification email to wait for. This has
        // to happen outside the create() above: email_verified_at is not
        // mass assignable, so passing it there is silently dropped and the
        // account is left unable to use the API (every route is behind the
        // "verified" middleware).
        $user->markEmailAsVerified();

        return response()->json([
            'data' => $user,
            'generated_password' => $generatedPassword,
        ], 201);
    }
}
