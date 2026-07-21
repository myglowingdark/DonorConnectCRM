<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Services\SaaS\EntitlementService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $campaigns = Campaign::query()
            ->forOrganization($orgId)
            ->withCount(['donors', 'donations', 'interactions', 'importBatches'])
            ->withSum('donations as revenue', 'amount')
            ->orderByDesc('id')
            ->get()
            ->map(function (Campaign $campaign) {
                $calls = (int) $campaign->interactions_count;
                $conversions = DonorInteraction::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('outcome', ['pledged', 'donated'])
                    ->count();

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'starts_at' => $campaign->starts_at?->toDateString(),
                    'ends_at' => $campaign->ends_at?->toDateString(),
                    'leads' => (int) $campaign->donors_count,
                    'donations_count' => (int) $campaign->donations_count,
                    'calls' => $calls,
                    'revenue' => (float) ($campaign->revenue ?? 0),
                    'conversion_rate' => $calls > 0 ? round(($conversions / $calls) * 100, 1) : 0,
                    'imports' => (int) $campaign->import_batches_count,
                ];
            });

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && $campaign->organization_id === $orgId, 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $from = $request->date('from') ?? ($campaign->starts_at?->copy() ?? now()->subDays(90));
        $to = $request->date('to') ?? now();

        $callsQuery = DonorInteraction::query()
            ->where('campaign_id', $campaign->id)
            ->whereBetween('contacted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $calls = (clone $callsQuery)->count();
        $conversions = (clone $callsQuery)->whereIn('outcome', ['pledged', 'donated'])->count();
        $outcomes = (clone $callsQuery)
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $donationsQuery = Donation::query()
            ->where('campaign_id', $campaign->id)
            ->whereBetween('donated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $revenue = (float) (clone $donationsQuery)->sum('amount');
        $donationsCount = (clone $donationsQuery)->count();
        $uniqueDonors = (clone $donationsQuery)->distinct('donor_id')->count('donor_id');

        $leads = Donor::query()->where('campaign_id', $campaign->id)->count();
        $contactedLeads = Donor::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('last_contacted_at')
            ->count();
        $donatedLeads = Donor::query()
            ->where('campaign_id', $campaign->id)
            ->where('total_donated', '>', 0)
            ->count();

        $leadConversion = $leads > 0 ? round(($donatedLeads / $leads) * 100, 1) : 0;

        $recentDonations = Donation::query()
            ->where('campaign_id', $campaign->id)
            ->with('donor:id,full_name,phone')
            ->latest('donated_at')
            ->limit(15)
            ->get(['id', 'donor_id', 'amount', 'donated_at', 'payment_method']);

        $imports = $campaign->importBatches()
            ->latest()
            ->limit(10)
            ->get(['id', 'original_filename', 'rows_created', 'rows_updated', 'rows_assigned', 'created_at']);

        $donors = Donor::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Campaigns/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'starts_at' => $campaign->starts_at?->toDateString(),
                'ends_at' => $campaign->ends_at?->toDateString(),
            ],
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'stats' => [
                'leads' => $leads,
                'contacted_leads' => $contactedLeads,
                'donated_leads' => $donatedLeads,
                'lead_conversion_rate' => $leadConversion,
                'calls' => $calls,
                'call_conversion_rate' => $calls > 0 ? round(($conversions / $calls) * 100, 1) : 0,
                'donations_count' => $donationsCount,
                'unique_donors' => $uniqueDonors,
                'revenue' => $revenue,
            ],
            'outcomes' => $outcomes,
            'recentDonations' => $recentDonations,
            'imports' => $imports,
            'donors' => $donors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $organization = Organization::findOrFail($orgId);
        $this->authorize('assignDonors', $organization);

        app(EntitlementService::class)->assertCanCreateCampaign($organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,paused,completed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $campaign = Campaign::query()->create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
            'starts_at' => $validated['starts_at'] ?? now()->toDateString(),
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign created.');
    }
}
