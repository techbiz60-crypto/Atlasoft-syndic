<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyndicTransition\StoreSyndicTransitionRequest;
use App\Models\SyndicMandateClosure;
use App\Models\User;
use App\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyndicTransitionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $residence = $request->user()->residence;

        return response()->json([
            'data' => [
                'current_lock' => $residence->currentMandateLock()?->load('closedBy', 'newAdmin'),
                'history' => $residence->mandateClosures()
                    ->with(['closedBy', 'newAdmin', 'reopenedBy'])
                    ->latest('closed_at')
                    ->get(),
            ],
        ]);
    }

    /**
     * Closes the books on everything the outgoing team entered, hands the
     * residence to a brand-new admin account, and cuts write access for the
     * whole outgoing team (admin included) — a deliberately irreversible
     * self-service action, gated by the admin's own password plus typing
     * the residence's exact name.
     */
    public function store(StoreSyndicTransitionRequest $request): JsonResponse
    {
        $residence = $request->user()->residence;
        $generatedPassword = Str::password(12);

        $newAdmin = DB::transaction(function () use ($request, $residence, $generatedPassword) {
            $closure = SyndicMandateClosure::create([
                'residence_id' => $residence->id,
                'closed_by_user_id' => $request->user()->id,
                'closed_at' => now(),
            ]);

            User::where('residence_id', $residence->id)
                ->whereIn('role', [Role::Admin, Role::Tresorier, Role::Conseil])
                ->whereNull('deactivated_at')
                ->update(['deactivated_at' => now()]);

            $newAdmin = User::create([
                'residence_id' => $residence->id,
                'name' => $request->validated('new_admin_name'),
                'email' => $request->validated('new_admin_email'),
                'role' => Role::Admin,
                'password' => Hash::make($generatedPassword),
            ]);

            $newAdmin->markEmailAsVerified();

            $closure->update(['new_admin_user_id' => $newAdmin->id]);

            return $newAdmin;
        });

        return response()->json([
            'data' => $newAdmin,
            'generated_password' => $generatedPassword,
        ], 201);
    }
}
