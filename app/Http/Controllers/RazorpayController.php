<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\Organization;
use App\Services\Payments\RazorpayService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    public function createOrder(Request $request, Donor $donor, RazorpayService $service): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $donor);
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && (int) $donor->organization_id === (int) $orgId, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'purpose' => ['nullable', 'string', 'max:100'],
        ]);

        $organization = Organization::query()->findOrFail($orgId);
        $checkout = $service->createOrder(
            $organization,
            $donor,
            $request->user(),
            (float) $data['amount'],
            $data['purpose'] ?? 'donation',
        );

        if ($request->wantsJson() || $request->header('X-Inertia') === null && $request->expectsJson()) {
            return response()->json($checkout);
        }

        return back()->with('success', 'Razorpay order created: '.$checkout['order_id']);
    }

    public function webhook(Request $request, Organization $organization, RazorpayService $service): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (! $service->verifyWebhookSignature($organization, $raw, $signature)) {
            Log::warning('Invalid Razorpay webhook signature', ['organization_id' => $organization->id]);

            return response()->json(['ok' => false], 400);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? '';

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $service->markPaidFromWebhook($organization, $payload);
        }

        return response()->json(['ok' => true]);
    }
}
