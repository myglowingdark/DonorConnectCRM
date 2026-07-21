<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\TelecallerCapacityBooking;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapacityBookingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $bookings = TelecallerCapacityBooking::query()
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->forOrganization($orgId))
            ->with(['organization:id,name', 'campaign:id,name', 'creator:id,name'])
            ->latest()
            ->paginate(20);

        $campaigns = Campaign::query()->forOrganization($orgId)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Capacity/Index', [
            'bookings' => $bookings,
            'campaigns' => $campaigns,
            'canApprove' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        TelecallerCapacityBooking::create([
            'organization_id' => $orgId,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'created_by' => $request->user()->id,
            'seats' => $validated['seats'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Capacity booking request submitted.');
    }

    public function approve(Request $request, TelecallerCapacityBooking $booking): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $booking->update(['status' => 'approved']);

        return back()->with('success', 'Booking approved.');
    }

    public function reject(Request $request, TelecallerCapacityBooking $booking): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $booking->update(['status' => 'rejected']);

        return back()->with('success', 'Booking rejected.');
    }
}
