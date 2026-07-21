<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /** @param  string  ...$roles */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $expanded = collect($roles)
            ->flatMap(fn (string $role) => $this->expandRoleAlias($role))
            ->unique()
            ->values();

        $allowed = $expanded
            ->map(fn (string $role) => UserRole::from($role));

        if (! $allowed->contains($user->role)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    /** @return list<string> */
    protected function expandRoleAlias(string $role): array
    {
        return match ($role) {
            'admin' => [
                UserRole::SuperAdmin->value,
                UserRole::OrganizationAdmin->value,
                UserRole::TeamLead->value,
                UserRole::Finance->value,
            ],
            'staff' => [
                UserRole::SuperAdmin->value,
                UserRole::OrganizationAdmin->value,
                UserRole::TeamLead->value,
                UserRole::Finance->value,
                UserRole::Viewer->value,
                UserRole::Volunteer->value,
            ],
            default => [$role],
        };
    }
}
