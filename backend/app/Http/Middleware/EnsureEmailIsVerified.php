<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            abort(403, 'Merci de vérifier votre adresse email avant de continuer.');
        }

        return $next($request);
    }
}
