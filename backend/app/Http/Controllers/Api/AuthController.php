<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Building;
use App\Models\ExpenseCategory;
use App\Models\Residence;
use App\Models\RevenueCategory;
use App\Models\Subscription;
use App\Models\User;
use App\Role;
use App\SubscriptionPlan;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $residence = Residence::create([
                'name' => $request->string('residence_name'),
                'lots_count' => $request->integer('lots_count'),
            ]);

            Building::create([
                'residence_id' => $residence->id,
                'name' => 'Bâtiment principal',
            ]);

            foreach (['Eau', 'Électricité', 'Gardiennage', 'Entretien', 'Assurance'] as $categoryName) {
                ExpenseCategory::create([
                    'residence_id' => $residence->id,
                    'name' => $categoryName,
                ]);
            }

            foreach (['Vente de biens/services', 'Location', 'Pénalités de retard', 'Divers'] as $categoryName) {
                RevenueCategory::create([
                    'residence_id' => $residence->id,
                    'name' => $categoryName,
                ]);
            }

            $plan = SubscriptionPlan::forLotsCount($residence->lots_count);

            Subscription::create([
                'residence_id' => $residence->id,
                'plan' => $plan,
                'trial_ends_at' => $plan === SubscriptionPlan::Free ? null : now()->addDays(15),
            ]);

            return User::create([
                'residence_id' => $residence->id,
                'role' => Role::Admin,
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'whatsapp_number' => $request->string('whatsapp_number'),
                'password' => $request->string('password'),
            ]);
        });

        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return response()->json(['user' => $user->load('residence')], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw new AuthenticationException('Identifiants invalides.');
        }

        $request->session()->regenerate();

        return response()->json(['user' => Auth::user()->load('residence')]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('residence')]);
    }
}
