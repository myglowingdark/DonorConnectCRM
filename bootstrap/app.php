<?php

use App\Http\Middleware\AuthenticateOrgApiToken;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureOrganizationSelected;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureTwoFactorForSuperAdmin;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentOrganization;
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
        $middleware->web(append: [
            HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            SetCurrentOrganization::class,
            EnsureSubscriptionActive::class,
            EnsureTwoFactorForSuperAdmin::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'org.selected' => EnsureOrganizationSelected::class,
            'subscription' => EnsureSubscriptionActive::class,
            'feature' => EnsureFeature::class,
            'org.api' => AuthenticateOrgApiToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'razorpay/webhook/*',
            'api/webhooks/*',
            'platform/razorpay/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
