<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\SubscriptionPlan;
use Illuminate\Support\Carbon;

/**
 * Force-expires a subscription immediately (e.g. a client stops paying, a
 * refund, fraud) instead of waiting for its period to run out naturally.
 * The free plan can't be deactivated — it has no expiry by design.
 */
class DeactivateSubscription
{
    /**
     * @throws \InvalidArgumentException if the subscription is on the free plan
     */
    public function handle(Subscription $subscription): void
    {
        if ($subscription->plan === SubscriptionPlan::Free) {
            throw new \InvalidArgumentException('Le plan gratuit ne peut pas être désactivé.');
        }

        $subscription->trial_ends_at = null;
        $subscription->current_period_end = Carbon::yesterday();
        $subscription->save();
    }
}
