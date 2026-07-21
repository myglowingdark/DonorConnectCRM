<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA is optional. Kept as a no-op so it can be re-enabled later without
 * removing it from the middleware stack.
 */
class EnsureTwoFactorForSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
