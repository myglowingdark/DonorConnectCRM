<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use App\Services\SaaS\EntitlementService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $org = null;
        $currentOrg = null;
        $organizations = [];

        if ($user) {
            $org = OrganizationContext::ensureFor($user);
            $currentOrg = $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'logo_path' => $org->logo_path,
                'brand_color' => $org->brand_color,
                'currency' => $org->currency,
                'initials' => $org->initials(),
            ] : null;

            $orgQuery = $user->isSuperAdmin()
                ? \App\Models\Organization::query()->where('is_active', true)->orderBy('name')
                : $user->activeOrganizations()->orderBy('name');

            $organizations = $orgQuery->get()->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'slug' => $o->slug,
                'logo_path' => $o->logo_path,
                'brand_color' => $o->brand_color,
                'initials' => $o->initials(),
            ])->values()->all();
        }

        $appName = (string) config('app.name', 'DonorConnect');
        $appBrand = trim((string) preg_replace('/\s+CRM$/i', '', $appName)) ?: 'DonorConnect';

        $authUser = null;
        if ($user) {
            $authUser = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'role_label' => $user->role?->label(),
                'phone' => $user->phone,
                'is_active' => $user->is_active,
            ];
        }

        $subscriptionLock = null;
        $features = [];
        $entitlements = null;
        $impersonating = (bool) $request->session()->has('impersonator_id');

        if ($user && $org) {
            $entitlementService = app(EntitlementService::class);
            $features = $entitlementService->featuresFor($org);
            $entitlements = [
                'limits' => $entitlementService->limitsFor($org),
                'features' => $features,
            ];

            $subscriptionLock = [
                'hard_locked' => $org->isHardLocked(),
                'soft_locked' => $org->isSubscriptionLocked(),
                'subscription_status' => $org->subscription_status,
                'trial_ends_at' => $org->trial_ends_at?->toIso8601String(),
            ];
        }

        return array_merge(parent::share($request), [
            'appName' => $appName,
            'appBrand' => $appBrand,
            'auth' => [
                'user' => $authUser,
            ],
            'currentOrganization' => $currentOrg,
            'organizations' => $organizations,
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'subscriptionLock' => $subscriptionLock,
            'features' => $features,
            'entitlements' => $entitlements,
            'impersonating' => $impersonating,
        ]);
    }
}
