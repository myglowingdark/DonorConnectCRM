<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Commissions\UpdateCommissionSettingsRequest;
use App\Models\CommissionSetting;
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
            [
                'individual_enabled' => true,
                'individual_default_percent' => 5,
                'shared_enabled' => false,
                'shared_percent' => 0,
                'shared_eligibility' => 'active_contributors',
                'volunteer_overrides' => [],
            ]
        );

        $volunteers = User::query()
            ->where('role', UserRole::Volunteer)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'override_percent' => $settings->volunteer_overrides[(string) $user->id] ?? null,
                'effective_percent' => $settings->rateForVolunteer($user->id),
            ]);

        return Inertia::render('Commissions/Settings', [
            'settings' => [
                'individual_enabled' => $settings->individual_enabled,
                'individual_default_percent' => (float) $settings->individual_default_percent,
                'shared_enabled' => $settings->shared_enabled,
                'shared_percent' => (float) $settings->shared_percent,
                'shared_eligibility' => $settings->shared_eligibility,
                'effective_from' => $settings->effective_from?->toDateString(),
                'effective_to' => $settings->effective_to?->toDateString(),
            ],
            'volunteers' => $volunteers,
            'canEdit' => $request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin(),
        ]);
    }

    public function updateSettings(UpdateCommissionSettingsRequest $request): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $data = $request->validated();
        $overrides = [];

        foreach ($data['volunteer_overrides'] ?? [] as $row) {
            if (! isset($row['volunteer_id'])) {
                continue;
            }
            if (! array_key_exists('percent', $row) || $row['percent'] === null || $row['percent'] === '') {
                continue;
            }
            $overrides[(string) $row['volunteer_id']] = (float) $row['percent'];
        }

        $settings = CommissionSetting::query()->firstOrCreate(['organization_id' => $orgId]);
        $settings->fill([
            'individual_enabled' => (bool) ($data['individual_enabled'] ?? false),
            'individual_default_percent' => $data['individual_default_percent'] ?? 0,
            'shared_enabled' => (bool) ($data['shared_enabled'] ?? false),
            'shared_percent' => $data['shared_percent'] ?? 0,
            'shared_eligibility' => $data['shared_eligibility'] ?? 'active_contributors',
            'volunteer_overrides' => $overrides,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
        ]);
        $settings->save();

        return back()->with('success', 'Commission / payment settings saved.');
    }
}
