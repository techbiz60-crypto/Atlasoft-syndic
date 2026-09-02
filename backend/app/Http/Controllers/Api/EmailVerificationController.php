<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Handles the signed link sent by email — no Sanctum auth required, since
     * the link may be opened from a mail client with no session at all. The
     * `signed` route middleware already guarantees the id/hash weren't
     * tampered with; the hash is re-checked here against the user's current
     * email in case it changed since the notification was sent.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(config('app.frontend_url').'/login?verified=1');
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Votre adresse email est déjà vérifiée.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email de vérification renvoyé.']);
    }
}
