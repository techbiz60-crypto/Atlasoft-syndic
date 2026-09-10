<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Blocks write actions for an account deactivated at a syndic handover
     * — read access (everything outside this middleware's route group)
     * stays available so the outgoing team can still consult its own
     * history.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDeactivated()) {
            abort(403, 'Ce compte a été désactivé suite à une passation de syndic. Accès en lecture seule uniquement.');
        }

        return $next($request);
    }
}
