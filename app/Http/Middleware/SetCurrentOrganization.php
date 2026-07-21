<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            OrganizationContext::ensureFor($user);
        }

        return $next($request);
    }
}
