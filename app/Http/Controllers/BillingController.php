<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanInvoice;
use App\Models\PlatformBillingSetting;
use App\Services\SaaS\EntitlementService;
use App\Services\SaaS\UsageMeterService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements, UsageMeterService $usage): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->with('plan')->findOrFail($orgId);

        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();

        $invoices = PlanInvoice::query()
            ->forOrganization($orgId)
            ->with('plan:id,name,code')
            ->latest()
            ->limit(20)
            ->get();

        $platformBilling = PlatformBillingSetting::current();

        return Inertia::render('Billing/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'subscription_status' => $organization->subscription_status,
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
                'plan' => $organization->plan,
                'custom_domain' => $organization->custom_domain,
                'email_from_name' => $organization->email_from_name,
                'brand_color' => $organization->brand_color,
            ],
            'plans' => $plans,
            'meters' => $usage->metersFor($organization),
            'limits' => $entitlements->limitsFor($organization),
            'features' => $entitlements->featuresFor($organization),
            'invoices' => $invoices,
            'platformBillingEnabled' => $platformBilling->enabled,
            'canManagePlans' => $request->user()->isSuperAdmin(),
            'canEditWhiteLabel' => $entitlements->hasFeature($organization, 'white_label') || $request->user()->isSuperAdmin(),
        ]);
    }

    public function assignPlan(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'subscription_status' => ['nullable', 'string', 'in:trial,active,past_due,suspended'],
            'trial_ends_at' => ['nullable', 'date'],
        ]);

        $organization->update([
            'plan_id' => $validated['plan_id'],
            'subscription_status' => $validated['subscription_status'] ?? $organization->subscription_status,
            'trial_ends_at' => $validated['trial_ends_at'] ?? $organization->trial_ends_at,
        ]);

        return back()->with('success', 'Plan assigned.');
    }

    public function updateWhiteLabel(Request $request, EntitlementService $entitlements): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);

        if (! $request->user()->isSuperAdmin()) {
            $entitlements->assertFeature($organization, 'white_label');
        }

        $validated = $request->validate([
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'email_from_name' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['nullable', 'string', 'max:20'],
        ]);

        $organization->update($validated);

        return back()->with('success', 'White-label settings saved.');
    }

    public function createInvoice(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->with('plan')->findOrFail($orgId);

        $amount = (int) ($organization->plan?->price_monthly ?? 0);

        if ($amount < 1) {
            return back()->with('error', 'Selected plan has no monthly platform fee.');
        }

        PlanInvoice::create([
            'organization_id' => $orgId,
            'plan_id' => $organization->plan_id,
            'invoice_number' => 'DC-'.strtoupper(Str::ulid()),
            'amount' => $amount,
            'currency' => 'INR',
            'status' => 'open',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        return back()->with('success', 'Platform invoice created.');
    }

    public function payInvoice(Request $request, PlanInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($invoice->organization_id === OrganizationContext::id(), 403);
        abort_if($invoice->status === 'paid', 422);

        if ($request->user()->isSuperAdmin() && $request->boolean('mark_paid')) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $invoice->organization?->update(['subscription_status' => 'active']);

            return back()->with('success', 'Invoice marked paid.');
        }

        $settings = PlatformBillingSetting::current();

        if (! $settings->enabled || blank($settings->razorpay_key_id) || blank($settings->razorpay_key_secret)) {
            return back()->with('error', 'Platform Razorpay billing is not enabled.');
        }

        $response = Http::withBasicAuth($settings->razorpay_key_id, $settings->razorpay_key_secret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $invoice->amount * 100,
                'currency' => $invoice->currency,
                'receipt' => $invoice->invoice_number,
                'notes' => [
                    'organization_id' => $invoice->organization_id,
                    'invoice_id' => $invoice->id,
                ],
            ]);

        if (! $response->successful()) {
            return back()->with('error', 'Could not create Razorpay order.');
        }

        $order = $response->json();
        $invoice->update(['razorpay_order_id' => $order['id'] ?? null, 'status' => 'open']);

        return back()->with('success', 'Razorpay order created. Complete payment to activate subscription.');
    }
}
