<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class SubscriptionsList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:list {--due= : Only show subscriptions ending within this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste les abonnements de toutes les résidences avec leur statut, pour le suivi manuel des renouvellements.';

    public function handle(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->with('residence')
            ->get()
            ->sortBy(fn (Subscription $subscription) => $subscription->days_remaining ?? PHP_INT_MAX);

        if ($this->option('due') !== null) {
            $threshold = (int) $this->option('due');
            $subscriptions = $subscriptions->filter(
                fn (Subscription $subscription) => $subscription->days_remaining !== null && $subscription->days_remaining <= $threshold
            );
        }

        if ($subscriptions->isEmpty()) {
            $this->info('Aucun abonnement à afficher.');

            return self::SUCCESS;
        }

        $this->table(
            ['Résidence', 'ID (réf. virement)', 'Plan', 'Statut', 'Cycle', 'Jours restants', 'Fin de période'],
            $subscriptions->map(fn (Subscription $subscription) => [
                $subscription->residence->name,
                $subscription->residence_id,
                $subscription->plan->label(),
                $subscription->status,
                $subscription->billing_cycle?->value ?? '—',
                $subscription->days_remaining ?? '—',
                ($subscription->current_period_end ?? $subscription->trial_ends_at)?->toDateString() ?? '—',
            ]),
        );

        return self::SUCCESS;
    }
}
