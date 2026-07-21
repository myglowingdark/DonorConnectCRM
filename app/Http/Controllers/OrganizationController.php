<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Models\CommissionCycle;
use App\Models\CommissionSetting;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\PlatformCommissionSetting;
use App\Models\RazorpayPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SaaS\EntitlementService;
use App\Services\SaaS\OrgHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount(['donors', 'users'])
            ->with('apiConnection')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('Organizations/Form', [
            'organization' => null,
        ]);
    }

    public function store(StoreOrganizationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['brand_color'] = $data['brand_color'] ?? '#1e3a8a';
        $data['timezone'] = $data['timezone'] ?? 'Asia/Kolkata';
        $data['currency'] = $data['currency'] ?? 'INR';

        if (array_key_exists('donors_limit', $data) && $data['donors_limit'] === '') {
            $data['donors_limit'] = null;
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);

        $organization = Organization::create($data);

        CommissionSetting::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            PlatformCommissionSetting::current()->defaultsForOrganization()
        );

        $auditLogger->log('organization.created', $organization, null, $organization->toArray(), $organization->id);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created.');
    }

    public function show(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        return $this->renderProfile($organization);
    }

    public function current(Request $request): Response
    {
        $orgId = \App\Support\OrganizationContext::id();
        abort_unless($orgId, 403);
        $organization = Organization::query()->findOrFail($orgId);
        $this->authorize('view', $organization);

        return $this->renderProfile($organization);
    }

    protected function renderProfile(Organization $organization): Response
    {
        $organization->load(['apiConnection', 'commissionSetting', 'messagingSetting', 'plan']);
        $organization->loadCount(['donors', 'users', 'donations']);

        $entitlements = app(EntitlementService::class);
        $health = app(OrgHealthService::class)->assess($organization);

        $volunteers = User::query()
            ->where('role', 'volunteer')
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'is_internal_telecaller', 'is_active']);

        $recentDonations = Donation::query()
            ->forOrganization($organization->id)
            ->with('donor:id,full_name')
            ->latest('donated_at')
            ->limit(10)
            ->get();

        $recentPayments = RazorpayPayment::query()
            ->forOrganization($organization->id)
            ->with('donor:id,full_name')
            ->latest()
            ->limit(10)
            ->get();

        $latestCycle = CommissionCycle::query()
            ->forOrganization($organization->id)
            ->orderByDesc('period')
            ->first();

        return Inertia::render('Organizations/Show', [
            'organization' => [
                ...$organization->toArray(),
                'donors_count' => $organization->donors_count,
                'users_count' => $organization->users_count,
                'donations_count' => $organization->donations_count,
                'has_razorpay_secret' => filled($organization->razorpay_key_secret),
                'razorpay_key_secret' => null,
                'razorpay_webhook_secret' => null,
            ],
            'apiConnection' => $organization->apiConnection?->toSafeArray(),
            'canManageSync' => request()->user()?->can('manageSync', $organization) ?? false,
            'volunteers' => $volunteers,
            'recentDonations' => $recentDonations,
            'recentPayments' => $recentPayments,
            'latestCycle' => $latestCycle,
            'monthCollection' => Donation::query()
                ->forOrganization($organization->id)
                ->where('donated_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'canEdit' => request()->user()?->can('update', $organization) ?? false,
            'plan' => $organization->plan,
            'subscription' => [
                'status' => $organization->subscription_status,
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
            ],
            'usageMeters' => $organization->usageMeters(),
            'limits' => $entitlements->limitsFor($organization),
            'features' => $entitlements->featuresFor($organization),
            'health' => $health,
        ]);
    }

    public function updateRazorpay(Request $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'razorpay_enabled' => ['sometimes', 'boolean'],
            'razorpay_key_id' => ['nullable', 'string', 'max:255'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        if (! array_key_exists('razorpay_key_secret', $data) || blank($data['razorpay_key_secret'])) {
            unset($data['razorpay_key_secret']);
        }
        if (! array_key_exists('razorpay_webhook_secret', $data) || blank($data['razorpay_webhook_secret'])) {
            unset($data['razorpay_webhook_secret']);
        }

        $old = $organization->only([
            'razorpay_enabled', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret',
        ]);

        $organization->fill([
            ...$data,
            'razorpay_enabled' => $request->boolean('razorpay_enabled'),
        ]);
        $organization->save();

        $auditLogger->log(
            'organization.razorpay_updated',
            $organization,
            $old,
            $organization->only([
                'razorpay_enabled', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret',
            ]),
            $organization->id,
            $request->user(),
        );

        return back()->with('success', 'Razorpay settings saved.');
    }

    public function edit(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        return Inertia::render('Organizations/Form', [
            'organization' => $organization,
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $old = $organization->only(['name', 'slug', 'brand_color', 'timezone', 'currency', 'is_active', 'donors_limit', 'attribution_window_days', 'logo_path']);
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if (array_key_exists('donors_limit', $data) && $data['donors_limit'] === '') {
            $data['donors_limit'] = null;
        }
        if (array_key_exists('attribution_window_days', $data) && ($data['attribution_window_days'] === '' || $data['attribution_window_days'] === null)) {
            $data['attribution_window_days'] = 3;
        }

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);
        $organization->update($data);

        $auditLogger->log('organization.updated', $organization, $old, $organization->fresh()->toArray(), $organization->id);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated.');
    }
}
