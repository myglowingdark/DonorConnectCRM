<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\Organization;
use App\Services\Payments\RazorpayService;
use App\Services\SaaS\EntitlementService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueDialerController extends Controller
{
    public function queue(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        abort_unless($user?->isVolunteer() || $user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $donor = Donor::query()
            ->forOrganization($orgId)
            ->when($user->isVolunteer(), fn ($q) => $q->assignedTo($user->id))
            ->callable()
            ->orderForNextCall()
            ->with(['activeAssignment.volunteer', 'campaign:id,name'])
            ->first();

        $payload = [
            'donor' => $donor,
            'queue_empty' => $donor === null,
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Dialer/Queue', $payload);
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

        $donor = Donor::query()->forOrganization($orgId)->findOrFail($validated['donor_id']);

        if ($user->isVolunteer()) {
            abort_unless(
                $donor->activeAssignment?->volunteer_id === $user->id,
                403,
            );
        }

        $donor->update(['next_follow_up_at' => now()->addDay()]);

        $next = Donor::query()
            ->forOrganization($orgId)
            ->when($user->isVolunteer(), fn ($q) => $q->assignedTo($user->id))
            ->where('id', '!=', $donor->id)
            ->callable()
            ->orderForNextCall()
            ->first();

        if ($request->expectsJson() || $request->wantsJson()) {
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
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        abort_unless($user?->isVolunteer() || $user?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && $donor->organization_id === $orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $entitlements->assertFeature($organization, 'razorpay');

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $amount = (float) ($validated['amount'] ?? $donor->interactions()
            ->where('outcome', 'pledged')
            ->latest('contacted_at')
            ->value('pledged_amount') ?? 0);

        abort_if($amount < 1, 422, 'Amount is required when no pledge amount exists.');

        $result = $razorpay->createPaymentLink($organization, $donor, $amount);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('success', 'Payment link created.');
    }
}
