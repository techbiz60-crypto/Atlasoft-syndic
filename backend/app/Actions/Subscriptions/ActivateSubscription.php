<?php

namespace App\Actions\Subscriptions;

use App\BillingCycle;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\SubscriptionPlan;
use Illuminate\Support\Carbon;

/**
 * Validates a manually-confirmed bank transfer: extends the subscription's
 * period and records the corresponding invoice. Shared by the
 * `subscriptions:activate` Artisan command and the platform-admin API so
 * both stay in sync.
 */
class ActivateSubscription
{
    /**
     * @throws \InvalidArgumentException if the plan has no fixed price and no $amount override is given
     */
    public function handle(Subscription $subscription, BillingCycle $cycle, ?SubscriptionPlan $plan = null, ?int $amount = null): SubscriptionInvoice
    {
        if ($plan) {
            $subscription->plan = $plan;
        }

        $listPrice = $cycle === BillingCycle::Annual
            ? $subscription->plan->annualPrice()
            : $subscription->plan->monthlyPrice();

        $amount ??= $listPrice;

        if ($amount === null) {
            throw new \InvalidArgumentException("Ce plan n'a pas de prix fixe (sur devis) : un montant explicite est requis.");
        }

        // Renew from the current period's end if still running (early renewal),
        // otherwise from today — never lets a renewal shorten a still-active period.
        $periodStart = $subscription->current_period_end?->isFuture() ? $subscription->current_period_end : Carbon::now();
        $periodEnd = $cycle === BillingCycle::Annual
            ? $periodStart->copy()->addYear()
            : $periodStart->copy()->addMonth();

        $subscription->billing_cycle = $cycle;
        $subscription->trial_ends_at = null;
        $subscription->current_period_end = $periodEnd;
        $subscription->save();

        return SubscriptionInvoice::create([
            'residence_id' => $subscription->residence_id,
            'subscription_id' => $subscription->id,
            'plan' => $subscription->plan,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'validated_at' => Carbon::now(),
        ]);
    }
}
