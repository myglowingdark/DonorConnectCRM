<?php

namespace App\Services\SaaS;

use App\Models\Plan;

class PlanCatalog
{
    /** @return array<string, Plan> */
    public function seed(): array
    {
        $definitions = [
            'free' => [
                'name' => 'Free',
                'price_monthly' => 0,
                'seats_limit' => 3,
                'donors_limit' => 500,
                'campaigns_limit' => 2,
                'whatsapp_monthly_limit' => 100,
                'telecaller_hours_monthly' => null,
                'imports_monthly_limit' => 5,
                'features' => ['messaging', 'reports'],
                'sort_order' => 1,
            ],
            'growth' => [
                'name' => 'Growth',
                'price_monthly' => 4999,
                'seats_limit' => 15,
                'donors_limit' => 5000,
                'campaigns_limit' => 20,
                'whatsapp_monthly_limit' => 2000,
                'telecaller_hours_monthly' => null,
                'imports_monthly_limit' => 50,
                'features' => ['messaging', 'reports', 'razorpay', 'api', 'webhooks'],
                'sort_order' => 2,
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price_monthly' => 0,
                'seats_limit' => null,
                'donors_limit' => null,
                'campaigns_limit' => null,
                'whatsapp_monthly_limit' => null,
                'telecaller_hours_monthly' => null,
                'imports_monthly_limit' => null,
                'features' => [
                    'messaging',
                    'reports',
                    'razorpay',
                    'api',
                    'webhooks',
                    'internal_telecallers',
                    'white_label',
                    'capacity_booking',
                ],
                'sort_order' => 3,
            ],
        ];

        $plans = [];

        foreach ($definitions as $code => $attributes) {
            $plans[$code] = Plan::query()->updateOrCreate(
                ['code' => $code],
                array_merge($attributes, ['is_active' => true]),
            );
        }

        return $plans;
    }
}
