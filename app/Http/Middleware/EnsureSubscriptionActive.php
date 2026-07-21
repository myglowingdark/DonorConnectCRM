<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = OrganizationContext::organization();

        $lockState = [
            'hard_locked' => false,
            'soft_locked' => false,
            'subscription_status' => $organization?->subscription_status,
            'trial_ends_at' => $organization?->trial_ends_at?->toIso8601String(),
        ];

        if ($organization) {
            $lockState['hard_locked'] = $organization->isHardLocked();
            $lockState['soft_locked'] = $organization->isSubscriptionLocked();
        }

        $request->attributes->set('subscription_lock', $lockState);
        View::share('subscriptionLock', $lockState);

        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        if ($organization && $organization->isHardLocked() && ! $this->isExemptRoute($request)) {
            abort(403, 'This organization has been suspended. Please update billing to restore access.');
        }

        if ($organization && $organization->isSubscriptionLocked() && ! $request->session()->has('subscription_warning_shown')) {
            $message = match ($organization->subscription_status) {
                'past_due' => 'Your subscription payment is past due. Some features may be limited until payment is received.',
                'trial' => 'Your trial has expired. Please choose a plan to continue using DonorConnect.',
                default => 'Your subscription requires attention. Please review billing settings.',
            };

            $request->session()->flash('warning', $message);
            $request->session()->put('subscription_warning_shown', true);
        }

        return $next($request);
    }

    protected function isExemptRoute(Request $request): bool
    {
        if ($request->routeIs('profile.*', 'organization.profile', 'billing.*')) {
            return true;
        }

        return $request->is('profile', 'profile/*', 'organization/profile', 'billing', 'billing/*');
    }
}
