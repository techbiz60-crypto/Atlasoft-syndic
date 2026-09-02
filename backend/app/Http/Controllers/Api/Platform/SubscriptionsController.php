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
        $residences = Residence::with(['subscription', 'users' => fn ($query) => $query->where('role', Role::Admin)])
            ->orderBy('name')
            ->get()
            ->map(function (Residence $residence) {
                $admin = $residence->users->first();

                return [
                    'residence_id' => $residence->id,
                    'residence_name' => $residence->name,
                    'lots_count' => $residence->lots_count,
                    'admin_name' => $admin?->name,
                    'admin_email' => $admin?->email,
                    'admin_whatsapp' => $admin?->whatsapp_number,
                    'subscription' => $residence->subscription,
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
