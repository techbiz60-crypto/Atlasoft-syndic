<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsWritable
{
    /**
     * Blocks write actions once a residence's subscription has expired
     * (trial or paid period both over) — read access stays available.
     * A residence with no subscription row yet (legacy data, or the
     * platform team hasn't set one up) is treated as writable: this
     * middleware only locks down residences with an explicit, expired
     * subscription.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = $request->user()?->residence?->subscription;

        if ($subscription && ! $subscription->is_writable) {
            abort(403, 'Abonnement expiré. Contactez-nous pour le renouveler — les données déjà saisies restent consultables.');
        }

        return $next($request);
    }
}
