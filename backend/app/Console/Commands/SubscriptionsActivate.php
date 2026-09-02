<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\ActivateSubscription;
use App\BillingCycle;
use App\Models\Subscription;
use App\SubscriptionPlan;
use Illuminate\Console\Command;

class SubscriptionsActivate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:activate
        {residence : Residence ID (the payment reference)}
        {cycle : monthly or annual}
        {--plan= : Override the plan (starter, standard, plus, premium, custom) — defaults to keeping the current plan}
        {--amount= : Amount invoiced, in DH — required for the custom (sur devis) plan, optional otherwise (defaults to the plan\'s list price)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Active/renouvelle l'abonnement d'une résidence après validation manuelle du virement reçu par WhatsApp, et enregistre la facture correspondante.";

    public function handle(ActivateSubscription $activateSubscription): int
    {
        $subscription = Subscription::withoutGlobalScopes()
            ->where('residence_id', $this->argument('residence'))
            ->first();

        if (! $subscription) {
            $this->error("Aucun abonnement trouvé pour la résidence #{$this->argument('residence')}.");

            return self::FAILURE;
        }

        $cycle = BillingCycle::tryFrom($this->argument('cycle'));

        if (! $cycle) {
            $this->error("Cycle invalide : utilisez 'monthly' ou 'annual'.");

            return self::FAILURE;
        }

        $plan = null;

        if ($this->option('plan')) {
            $plan = SubscriptionPlan::tryFrom($this->option('plan'));

            if (! $plan) {
                $this->error('Plan invalide : starter, standard, plus, premium ou custom.');

                return self::FAILURE;
            }
        }

        $amount = $this->option('amount') !== null ? (int) $this->option('amount') : null;

        try {
            $invoice = $activateSubscription->handle($subscription, $cycle, $plan, $amount);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Abonnement de la résidence #{$subscription->residence_id} activé jusqu'au {$invoice->period_end->toDateString()} ({$subscription->plan->label()}, {$invoice->amount} DH facturés).");

        return self::SUCCESS;
    }
}
