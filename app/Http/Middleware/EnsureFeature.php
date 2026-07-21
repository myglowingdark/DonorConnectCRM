<?php

namespace App\Http\Middleware;

use App\Services\SaaS\EntitlementService;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        $organization = OrganizationContext::organization();

        if (! $organization) {
            abort(403, 'No organization selected.');
        }

        if (! app(EntitlementService::class)->hasFeature($organization, $feature)) {
            abort(403, "The \"{$feature}\" feature is not available on your current plan.");
        }

        return $next($request);
    }
}
