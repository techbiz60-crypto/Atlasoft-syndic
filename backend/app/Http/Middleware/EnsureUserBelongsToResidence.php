<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToResidence
{
    /**
     * Blocks residence-scoped app routes for a user with no residence (i.e.
     * a platform admin account). Without this, TenantScope silently skips
     * filtering for such a user (its check is "residence_id !== null"),
     * which would otherwise expose every residence's data through the
     * regular tenant API surface instead of just the dedicated
     * /api/platform/* endpoints meant for cross-tenant access.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->residence_id === null) {
            abort(403, 'Ce compte ne peut pas accéder aux données d\'une résidence.');
        }

        return $next($request);
    }
}
