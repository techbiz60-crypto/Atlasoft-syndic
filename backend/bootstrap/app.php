<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSubscriptionIsWritable;
use App\Http\Middleware\EnsureUserBelongsToResidence;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'permission' => EnsureHasPermission::class,
            'subscription.active' => EnsureSubscriptionIsWritable::class,
            'verified' => EnsureEmailIsVerified::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'tenant.user' => EnsureUserBelongsToResidence::class,
        ]);

        // This app is API-only (no Blade "login" page) — an unauthenticated
        // request must always get a 401 JSON response, never an attempted
        // redirect to a "login" route that doesn't exist here.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
