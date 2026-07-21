<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            OrganizationContext::ensureFor($user);
        }

        if (! OrganizationContext::id() || ! OrganizationContext::organization()) {
            OrganizationContext::set(null);

            if ($user?->isSuperAdmin()) {
                return redirect()
                    ->route('organizations.index')
                    ->with('error', 'Create or select an organization to use this area.');
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Please select an organization to continue.');
        }

        return $next($request);
    }
}
