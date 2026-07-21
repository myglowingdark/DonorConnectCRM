<?php

namespace App\Http\Middleware;

use App\Models\OrganizationApiToken;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrgApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->bearerToken();

        if (! $header) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hash = hash('sha256', $header);

        $token = OrganizationApiToken::query()
            ->where('token_hash', $hash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('org_api_token', $token);
        OrganizationContext::set($token->organization_id);

        return $next($request);
    }
}
