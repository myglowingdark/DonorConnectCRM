<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\RazorpayPayment;
use App\Services\Payments\RazorpayService;
use App\Services\SaaS\EntitlementService;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QueueDialerController extends Controller
{
    public function queue(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isVolunteer() || $user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $donor = Donor::query()
            ->forOrganization($orgId)
            ->when($user->isVolunteer(), fn ($q) => $q->assignedTo($user->id))
            ->needsCall()
            ->orderForNextCall()
            ->with(['activeAssignment.volunteer', 'campaign:id,name'])
            ->first();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'donor' => $donor,
                'queue_empty' => $donor === null,
            ]);
        }

        return Inertia::render('Dialer/Queue', [
            'donor' => $donor,
            'queue_empty' => $donor === null,
        ]);
    }

    public function skip(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isVolunteer() || $user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'donor_id' => ['required', 'integer', 'exists:donors,id'],
        ]);

        $donor = Donor::query()
            ->forOrganization($orgId)
            ->with('activeAssignment')
            ->findOrFail($validated['donor_id']);

        if ($user->isVolunteer()) {
            $assigned = $donor->activeAssignment?->volunteer_id === $user->id
                || $donor->assignments()
                    ->where('volunteer_id', $user->id)
                    ->where('is_active', true)
                    ->exists();
            abort_unless($assigned, 403);
        }

        // Defer until tomorrow so needsCall() drops them from the queue.
        $donor->update(['next_follow_up_at' => now()->addDay()->startOfDay()->setTime(9, 0)]);

        if ($request->expectsJson() || $request->wantsJson()) {
            $next = Donor::query()
                ->forOrganization($orgId)
                ->when($user->isVolunteer(), fn ($q) => $q->assignedTo($user->id))
                ->where('id', '!=', $donor->id)
                ->needsCall()
                ->orderForNextCall()
                ->first();

            return response()->json([
                'skipped' => $donor->id,
                'next' => $next,
            ]);
        }

        return redirect()->route('dialer.queue');
    }

    public function paymentLink(
        Request $request,
        Donor $donor,
        RazorpayService $razorpay,
        EntitlementService $entitlements,
        WordPressDonorSyncService $bridge,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        abort_unless($user?->isVolunteer() || $user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && $donor->organization_id === $orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $entitlements->assertFeature($organization, 'razorpay');

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
            'via' => ['nullable', 'string', 'in:crm,wordpress,auto'],
        ]);

        $amount = (float) ($validated['amount'] ?? $donor->interactions()
            ->where('outcome', 'pledged')
            ->latest('contacted_at')
            ->value('pledged_amount') ?? 0);

        abort_if($amount < 1, 422, 'Amount is required when no pledge amount exists.');

        $via = $validated['via'] ?? 'auto';
        $result = null;
        $source = 'crm';

        $canUseCrmKeys = $organization->razorpay_enabled
            && filled($organization->razorpay_key_id)
            && filled($organization->razorpay_key_secret);

        $connection = OrganizationApiConnection::query()
            ->forOrganization($orgId)
            ->where('is_active', true)
            ->first();

        try {
            if ($via === 'crm' || ($via === 'auto' && $canUseCrmKeys)) {
                $result = $razorpay->createPaymentLink($organization, $donor, $amount, 'CRM payment request');
                $source = 'crm';
            } elseif ($connection) {
                $bridgeResult = $bridge->createPaymentLinkViaBridge($connection, $donor, $amount, 'CRM payment request');
                if (! ($bridgeResult['ok'] ?? false)) {
                    throw ValidationException::withMessages([
                        'razorpay' => $bridgeResult['message'] ?? 'WordPress payment link failed.',
                    ]);
                }

                $payment = RazorpayPayment::create([
                    'organization_id' => $organization->id,
                    'donor_id' => $donor->id,
                    'created_by' => $user->id,
                    'razorpay_order_id' => $bridgeResult['payment_link_id'] ?? null,
                    'amount' => $amount,
                    'currency' => $organization->currency ?: 'INR',
                    'status' => 'created',
                    'purpose' => 'CRM payment request (via WordPress)',
                    'payload' => $bridgeResult['raw'] ?? $bridgeResult,
                ]);

                $result = [
                    'payment' => $payment,
                    'payment_link_id' => $bridgeResult['payment_link_id'] ?? null,
                    'short_url' => $bridgeResult['short_url'] ?? null,
                    'amount' => (int) round($amount * 100),
                    'currency' => $organization->currency ?: 'INR',
                    'via' => 'wordpress',
                ];
                $source = 'wordpress';
            } elseif ($canUseCrmKeys) {
                $result = $razorpay->createPaymentLink($organization, $donor, $amount, 'CRM payment request');
                $source = 'crm';
            } else {
                throw ValidationException::withMessages([
                    'razorpay' => 'Razorpay is not configured. Sync keys from WordPress (Org profile → WordPress site → Sync Razorpay) or enter keys on Org profile.',
                ]);
            }
        } catch (ValidationException $e) {
            // Auto-fallback: CRM keys failed → try WordPress bridge.
            if ($via === 'auto' && $connection && $source === 'crm') {
                $bridgeResult = $bridge->createPaymentLinkViaBridge($connection, $donor, $amount, 'CRM payment request');
                if ($bridgeResult['ok'] ?? false) {
                    $payment = RazorpayPayment::create([
                        'organization_id' => $organization->id,
                        'donor_id' => $donor->id,
                        'created_by' => $user->id,
                        'razorpay_order_id' => $bridgeResult['payment_link_id'] ?? null,
                        'amount' => $amount,
                        'currency' => $organization->currency ?: 'INR',
                        'status' => 'created',
                        'purpose' => 'CRM payment request (via WordPress)',
                        'payload' => $bridgeResult['raw'] ?? $bridgeResult,
                    ]);
                    $result = [
                        'payment' => $payment,
                        'payment_link_id' => $bridgeResult['payment_link_id'] ?? null,
                        'short_url' => $bridgeResult['short_url'] ?? null,
                        'amount' => (int) round($amount * 100),
                        'currency' => $organization->currency ?: 'INR',
                        'via' => 'wordpress',
                    ];
                    $source = 'wordpress';
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        $shortUrl = $result['short_url'] ?? null;
        $message = $shortUrl
            ? 'Payment link created via '.$source.': '.$shortUrl
            : 'Payment request created via '.$source.'.';

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                ...$result,
                'via' => $source,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
