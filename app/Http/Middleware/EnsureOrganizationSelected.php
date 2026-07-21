<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('current_organization_id')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Please select an organization to continue.');
        }

        return $next($request);
    }
}
