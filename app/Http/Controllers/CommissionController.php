<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Commissions\UpdateCommissionSettingsRequest;
use App\Models\CommissionSetting;
use App\Models\PlatformCommissionSetting;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    public function settings(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $settings = CommissionSetting::query()->firstOrCreate(
            ['organization_id' => $orgId],
            PlatformCommissionSetting::current()->defaultsForOrganization()
        );

        $allVolunteers = User::query()
            ->where('role', UserRole::Volunteer)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_internal_telecaller']);

        $orgVolunteers = $allVolunteers
            ->where('is_internal_telecaller', false)
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'override_percent' => $settings->volunteer_overrides[(string) $user->id] ?? null,
                'effective_percent' => $settings->rateForVolunteer($user->id, false),
            ]);

        $isSuperAdmin = $request->user()->isSuperAdmin();

        // Internal (platform) telecaller rates are Super Admin only — org admins must not see them.
        $internalVolunteers = collect();
        $internalSettings = [];
        if ($isSuperAdmin) {
            $internalVolunteers = $allVolunteers
                ->where('is_internal_telecaller', true)
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'override_percent' => $settings->internal_volunteer_overrides[(string) $user->id] ?? null,
                    'effective_percent' => $settings->rateForVolunteer($user->id, true),
                ]);

            $internalSettings = [
                'internal_individual_enabled' => $settings->internal_individual_enabled,
                'internal_individual_default_percent' => (float) $settings->internal_individual_default_percent,
                'internal_shared_enabled' => $settings->internal_shared_enabled,
                'internal_shared_percent' => (float) $settings->internal_shared_percent,
            ];
        }

        return Inertia::render('Commissions/Settings', [
            'settings' => array_merge([
                'individual_enabled' => $settings->individual_enabled,
                'individual_default_percent' => (float) $settings->individual_default_percent,
                'shared_enabled' => $settings->shared_enabled,
                'shared_percent' => (float) $settings->shared_percent,
                'shared_eligibility' => $settings->shared_eligibility,
                'effective_from' => $settings->effective_from?->toDateString(),
                'effective_to' => $settings->effective_to?->toDateString(),
            ], $internalSettings),
            'volunteers' => $orgVolunteers,
            'internalVolunteers' => $internalVolunteers,
            'canEdit' => $isSuperAdmin || $request->user()->isOrganizationAdmin(),
            'canEditInternal' => $isSuperAdmin,
        ]);
    }

    public function updateSettings(UpdateCommissionSettingsRequest $request): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $data = $request->validated();
        $settings = CommissionSetting::query()->firstOrCreate(['organization_id' => $orgId]);

        $overrides = $this->parseOverrides($data['volunteer_overrides'] ?? []);

        $payload = [
            'individual_enabled' => (bool) ($data['individual_enabled'] ?? false),
            'individual_default_percent' => $data['individual_default_percent'] ?? 0,
            'shared_enabled' => (bool) ($data['shared_enabled'] ?? false),
            'shared_percent' => $data['shared_percent'] ?? 0,
            'shared_eligibility' => $data['shared_eligibility'] ?? 'active_contributors',
            'volunteer_overrides' => $overrides,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
        ];

        if ($request->user()->isSuperAdmin()) {
            $payload['internal_individual_enabled'] = (bool) ($data['internal_individual_enabled'] ?? false);
            $payload['internal_individual_default_percent'] = $data['internal_individual_default_percent'] ?? 0;
            $payload['internal_shared_enabled'] = (bool) ($data['internal_shared_enabled'] ?? false);
            $payload['internal_shared_percent'] = $data['internal_shared_percent'] ?? 0;
            $payload['internal_volunteer_overrides'] = $this->parseOverrides($data['internal_volunteer_overrides'] ?? []);
        }

        $settings->fill($payload);
        $settings->save();

        return back()->with('success', 'Commission / payment settings saved.');
    }

    /**
     * @param  array<int, array{volunteer_id?: mixed, percent?: mixed}>  $rows
     * @return array<string, float>
     */
    protected function parseOverrides(array $rows): array
    {
        $overrides = [];
        foreach ($rows as $row) {
            if (! isset($row['volunteer_id'])) {
                continue;
            }
            if (! array_key_exists('percent', $row) || $row['percent'] === null || $row['percent'] === '') {
                continue;
            }
            $overrides[(string) $row['volunteer_id']] = (float) $row['percent'];
        }

        return $overrides;
    }
}
