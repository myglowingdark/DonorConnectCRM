<?php

namespace App\Services\SaaS;

use App\Models\Organization;
use App\Models\PlatformMessagingSetting;
use Illuminate\Validation\ValidationException;

class EntitlementService
{
    /** @return array<string, int|null> */
    public function limitsFor(Organization $organization): array
    {
        $plan = $organization->plan;

        return [
            'donors' => $organization->donors_limit ?? $plan?->donors_limit,
            'seats' => $organization->seats_limit ?? $plan?->seats_limit,
            'campaigns' => $organization->campaigns_limit ?? $plan?->campaigns_limit,
            'whatsapp_monthly' => $organization->whatsapp_monthly_limit ?? $plan?->whatsapp_monthly_limit,
            'telecaller_hours_monthly' => $organization->telecaller_hours_monthly ?? $plan?->telecaller_hours_monthly,
            'imports_monthly' => $organization->imports_monthly_limit ?? $plan?->imports_monthly_limit,
        ];
    }

    /** @return list<string> */
    public function featuresFor(Organization $organization): array
    {
        $planFeatures = $organization->plan?->features ?? [];
        $overrides = $organization->feature_overrides ?? [];

        $enabled = collect($planFeatures);

        // Site-wide Super Admin module unlocks (before per-org overrides).
        if (PlatformMessagingSetting::current()->whatsapp_module_enabled) {
            $enabled->push('whatsapp');
        }

        foreach ($overrides as $feature => $enabledFlag) {
            if ($enabledFlag) {
                $enabled->push($feature);
            } else {
                $enabled = $enabled->reject(fn (string $f) => $f === $feature);
            }
        }

        return $enabled->unique()->values()->all();
    }

    public function hasFeature(Organization $organization, string $feature): bool
    {
        return in_array($feature, $this->featuresFor($organization), true);
    }

    public function assertFeature(Organization $organization, string $feature): void
    {
        if ($this->hasFeature($organization, $feature)) {
            return;
        }

        throw ValidationException::withMessages([
            'feature' => "The \"{$feature}\" feature is not available on your current plan.",
        ]);
    }

    public function assertCanCreateDonor(Organization $organization, int $count = 1): void
    {
        $organization->assertCanAcceptNewDonors($count);
    }

    public function assertCanCreateCampaign(Organization $organization): void
    {
        $limit = $this->limitsFor($organization)['campaigns'] ?? null;

        if ($limit === null) {
            return;
        }

        $current = $organization->campaigns()->count();

        if ($current < (int) $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'campaigns_limit' => "Campaign limit reached ({$current}/{$limit}). Upgrade your plan to add more campaigns.",
        ]);
    }

    public function assertCanCreateImport(Organization $organization): void
    {
        $limit = $this->limitsFor($organization)['imports_monthly'] ?? null;

        if ($limit === null) {
            return;
        }

        $current = app(UsageMeterService::class)->importsThisMonth($organization);

        if ($current < (int) $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'imports_limit' => "Monthly import limit reached ({$current}/{$limit}). Upgrade your plan for more imports.",
        ]);
    }

    public function assertCanCreateSeat(Organization $organization): void
    {
        $limit = $this->limitsFor($organization)['seats'] ?? null;

        if ($limit === null) {
            return;
        }

        $current = app(UsageMeterService::class)->seatsUsed($organization);

        if ($current < (int) $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'seats_limit' => "Seat limit reached ({$current}/{$limit}). Upgrade your plan to invite more team members.",
        ]);
    }

    public function assertCanSendWhatsApp(Organization $organization): void
    {
        $this->assertFeature($organization, 'whatsapp');

        $limit = $this->limitsFor($organization)['whatsapp_monthly'] ?? null;

        if ($limit === null) {
            return;
        }

        $current = app(UsageMeterService::class)->whatsappThisMonth($organization);

        if ($current < (int) $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'whatsapp_limit' => "Monthly WhatsApp limit reached ({$current}/{$limit}). Upgrade your plan to send more messages.",
        ]);
    }
}
