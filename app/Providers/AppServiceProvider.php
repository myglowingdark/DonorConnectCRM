<?php

namespace App\Providers;

use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use App\Policies\DonorPolicy;
use App\Policies\OrganizationApiConnectionPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Donor::class, DonorPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(OrganizationApiConnection::class, OrganizationApiConnectionPolicy::class);

        RateLimiter::for('sync', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
