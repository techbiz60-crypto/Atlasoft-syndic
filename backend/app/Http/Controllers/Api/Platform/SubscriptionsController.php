<?php

namespace App\Http\Controllers\Api\Platform;

use App\Actions\Subscriptions\ActivateSubscription;
use App\Actions\Subscriptions\DeactivateSubscription;
use App\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ActivateSubscriptionRequest;
use App\Models\Residence;
use App\Role;
use App\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class SubscriptionsController extends Controller
{
    public function index(): JsonResponse
    {
        $allResidences = Residence::with(['subscription', 'users' => fn ($query) => $query->where('role', Role::Admin)])
            ->orderBy('name')
            ->get();

        $byIp = $allResidences->whereNotNull('registration_ip')->groupBy('registration_ip')->filter(fn ($group) => $group->count() > 1);
        $byAddress = $allResidences->whereNotNull('address')
            ->groupBy(fn (Residence $residence) => mb_strtolower(trim($residence->address)))
            ->filter(fn ($group) => $group->count() > 1);

        $residences = $allResidences->map(function (Residence $residence) use ($byIp, $byAddress) {
            $admin = $residence->users->first();
            $lock = $residence->currentMandateLock()?->load('closedBy');

            $reasons = [];

            $sameIp = $byIp->get($residence->registration_ip)?->reject(fn (Residence $other) => $other->id === $residence->id);
            if ($sameIp?->isNotEmpty()) {
                $reasons[] = "Même IP d'inscription que : {$sameIp->pluck('name')->join(', ')}";
            }

            $sameAddress = $residence->address
                ? $byAddress->get(mb_strtolower(trim($residence->address)))?->reject(fn (Residence $other) => $other->id === $residence->id)
                : null;
            if ($sameAddress?->isNotEmpty()) {
                $reasons[] = "Même adresse que : {$sameAddress->pluck('name')->join(', ')}";
            }

            return [
                'residence_id' => $residence->id,
                'residence_name' => $residence->name,
                'address' => $residence->address,
                'lots_count' => $residence->lots_count,
                'registration_ip' => $residence->registration_ip,
                'admin_name' => $admin?->name,
                'admin_email' => $admin?->email,
                'admin_whatsapp' => $admin?->whatsapp_number,
                'subscription' => $residence->subscription,
                'mandate_lock' => $lock ? [
                    'id' => $lock->id,
                    'closed_at' => $lock->closed_at,
                    'closed_by_name' => $lock->closedBy?->name,
                ] : null,
                'duplicate_reasons' => $reasons,
            ];
        });

        return response()->json(['data' => $residences]);
    }

    public function activate(ActivateSubscriptionRequest $request, Residence $residence, ActivateSubscription $activateSubscription): JsonResponse
    {
        $subscription = $residence->subscription;

        abort_unless($subscription, 404, 'Cette résidence n\'a pas d\'abonnement.');

        $plan = $request->filled('plan') ? SubscriptionPlan::from($request->string('plan')->toString()) : null;
        $amount = $request->filled('amount') ? $request->integer('amount') : null;

        try {
            $invoice = $activateSubscription->handle($subscription, $request->enum('cycle', BillingCycle::class), $plan, $amount);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $invoice, 'subscription' => $subscription->fresh()], 201);
    }

    public function deactivate(Residence $residence, DeactivateSubscription $deactivateSubscription): JsonResponse
    {
        $subscription = $residence->subscription;

        abort_unless($subscription, 404, 'Cette résidence n\'a pas d\'abonnement.');

        try {
            $deactivateSubscription->handle($subscription);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $subscription->fresh()]);
    }
}
