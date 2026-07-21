<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->label(),
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                ] : null,
            ],
            'currentOrganization' => $currentOrg,
            'organizations' => $organizations,
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
