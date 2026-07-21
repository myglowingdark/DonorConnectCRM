<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Models\CallQualityRating;
use App\Models\Campaign;
use App\Models\CommissionCycle;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isStaff(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        return Inertia::render('Insights/Index', [
            'campaignRoi' => $this->campaignRoi($orgId),
            'volunteerLeaderboard' => $this->volunteerLeaderboard($orgId),
            'pledgeForecast' => $this->pledgeForecast($orgId),
            'superAdminBi' => $request->user()->isSuperAdmin() ? $this->superAdminBi() : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function campaignRoi(int $orgId): array
    {
        return Campaign::query()
            ->forOrganization($orgId)
            ->withSum('donations as revenue', 'amount')
            ->get()
            ->map(function (Campaign $campaign) use ($orgId) {
                $revenue = (float) ($campaign->revenue ?? 0);

                $commission = (float) CommissionCycle::query()
                    ->forOrganization($orgId)
                    ->where('period', now()->format('Y-m'))
                    ->value('payable_total') ?? 0;

                $messageCost = OutboundMessage::query()
                    ->forOrganization($orgId)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count() * 0.5;

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'revenue' => $revenue,
                    'estimated_cost' => round($commission + $messageCost, 2),
                    'roi' => round($revenue - $commission - $messageCost, 2),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function volunteerLeaderboard(int $orgId): array
    {
        return User::query()
            ->where('role', 'volunteer')
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->withCount([
                'interactions as calls_count' => fn ($q) => $q
                    ->forOrganization($orgId)
                    ->where('interaction_type', 'call')
                    ->where('contacted_at', '>=', now()->startOfMonth()),
                'interactions as conversions_count' => fn ($q) => $q
                    ->forOrganization($orgId)
                    ->whereIn('outcome', [CallOutcome::Pledged->value, CallOutcome::Donated->value])
                    ->where('contacted_at', '>=', now()->startOfMonth()),
            ])
            ->orderByDesc('calls_count')
            ->limit(20)
            ->get()
            ->map(function (User $user) use ($orgId) {
                $avgQuality = CallQualityRating::query()
                    ->forOrganization($orgId)
                    ->where('volunteer_id', $user->id)
                    ->avg('score');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'languages' => $user->languages ?? [],
                    'calls' => (int) $user->calls_count,
                    'conversions' => (int) $user->conversions_count,
                    'conversion_rate' => $user->calls_count > 0
                        ? round(($user->conversions_count / $user->calls_count) * 100, 1)
                        : 0,
                    'avg_quality' => $avgQuality ? round((float) $avgQuality, 1) : null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    protected function pledgeForecast(int $orgId): array
    {
        $pledged = (float) DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('outcome', CallOutcome::Pledged)
            ->where('contacted_at', '>=', now()->startOfMonth())
            ->sum('pledged_amount');

        $donated = (float) Donation::query()
            ->forOrganization($orgId)
            ->where('donated_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $agingPledges = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('outcome', CallOutcome::Pledged)
            ->where('contacted_at', '<', now()->subDays(14))
            ->whereNotNull('pledged_amount')
            ->count();

        return [
            'pledged_sum' => $pledged,
            'donated_sum' => $donated,
            'collection_gap' => round($pledged - $donated, 2),
            'aging_pledges' => $agingPledges,
        ];
    }

    /** @return array<string, mixed> */
    protected function superAdminBi(): array
    {
        $orgsByStatus = Organization::query()
            ->select('subscription_status', DB::raw('COUNT(*) as total'))
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        $trialEnding = Organization::query()
            ->where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->count();

        $topOrgs = Organization::query()
            ->withSum(['donations as revenue' => fn ($q) => $q->where('donated_at', '>=', now()->startOfMonth())], 'amount')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'name' => $org->name,
                'revenue' => (float) ($org->revenue ?? 0),
            ]);

        $internalTelecallers = User::query()
            ->where('is_internal_telecaller', true)
            ->where('role', 'volunteer')
            ->where('is_active', true)
            ->count();

        $activeAssignments = DonorAssignment::query()
            ->where('is_active', true)
            ->whereHas('volunteer', fn ($q) => $q->where('is_internal_telecaller', true))
            ->distinct('volunteer_id')
            ->count('volunteer_id');

        $utilization = $internalTelecallers > 0
            ? round(($activeAssignments / $internalTelecallers) * 100, 1)
            : 0;

        return [
            'org_counts_by_status' => $orgsByStatus,
            'trial_ending_soon' => $trialEnding,
            'top_orgs_by_revenue' => $topOrgs,
            'telecaller_utilization_percent' => $utilization,
        ];
    }
}
