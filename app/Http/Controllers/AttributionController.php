<?php

namespace App\Http\Controllers;

use App\Enums\AttributionStatus;
use App\Models\DonationAttribution;
use App\Models\Organization;
use App\Services\Commissions\AttributionService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttributionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $status = $request->string('status')->toString() ?: 'pending';
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = DonationAttribution::query()
            ->forOrganization($orgId)
            ->with(['donor:id,full_name', 'volunteer:id,name,email', 'donation:id,amount,currency,donated_at', 'reviewer:id,name'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return Inertia::render('Attributions/Index', [
            'filters' => ['status' => $status],
            'attributions' => $query->paginate(20)->withQueryString(),
            'counts' => [
                'pending' => DonationAttribution::query()->forOrganization($orgId)->where('status', AttributionStatus::Pending)->count(),
                'approved' => DonationAttribution::query()->forOrganization($orgId)->where('status', AttributionStatus::Approved)->count(),
                'rejected' => DonationAttribution::query()->forOrganization($orgId)->where('status', AttributionStatus::Rejected)->count(),
            ],
        ]);
    }

    public function approve(Request $request, DonationAttribution $attribution, AttributionService $service): RedirectResponse
    {
        $this->guardAttribution($request, $attribution);
        $note = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']])['admin_note'] ?? null;
        $service->approve($attribution, $request->user(), $note);

        return back()->with('success', 'Attribution approved.');
    }

    public function reject(Request $request, DonationAttribution $attribution, AttributionService $service): RedirectResponse
    {
        $this->guardAttribution($request, $attribution);
        $note = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']])['admin_note'] ?? null;
        $service->reject($attribution, $request->user(), $note);

        return back()->with('success', 'Attribution rejected.');
    }

    protected function guardAttribution(Request $request, DonationAttribution $attribution): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && (int) $attribution->organization_id === (int) $orgId, 403);
    }
}
