<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\SaaS\PlanCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Foundation';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'brand_color' => fake()->randomElement(['#1e3a8a', '#0f766e', '#166534', '#7c2d12']),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            if ($organization->plan_id !== null) {
                return;
            }

            $plan = Plan::query()->where('code', 'free')->first();

            if (! $plan) {
                app(PlanCatalog::class)->seed();
                $plan = Plan::query()->where('code', 'free')->first();
            }

            if (! $plan) {
                return;
            }

            $organization->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => $organization->subscription_status ?: 'trial',
                'trial_ends_at' => $organization->trial_ends_at ?: now()->addDays(30),
                'donors_limit' => $organization->donors_limit ?? $plan->donors_limit,
                'seats_limit' => $organization->seats_limit ?? $plan->seats_limit,
                'campaigns_limit' => $organization->campaigns_limit ?? $plan->campaigns_limit,
                'whatsapp_monthly_limit' => $organization->whatsapp_monthly_limit ?? $plan->whatsapp_monthly_limit,
                'imports_monthly_limit' => $organization->imports_monthly_limit ?? $plan->imports_monthly_limit,
            ])->saveQuietly();
        });
    }
}
