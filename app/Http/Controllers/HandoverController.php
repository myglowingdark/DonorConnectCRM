<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\DonorHandover;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\HandoverService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $volunteers = User::query()
            ->where('role', 'volunteer')
            ->whereHas(
                'organizations',
                fn ($q) => $q->where('organizations.id', $orgId)->where('organization_user.is_active', true)
            )
            ->withCount([
                'assignments as donor_count' => fn ($q) => $q->where('organization_id', $orgId)->where('is_active', true),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'languages']);

        $fromId = $request->integer('from_volunteer_id') ?: null;
        $donors = collect();
        if ($fromId) {
            $donors = Donor::query()
                ->forOrganization($orgId)
                ->assignedTo($fromId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'phone', 'email', 'next_follow_up_at']);
        }

        $history = DonorHandover::query()
            ->forOrganization($orgId)
            ->with(['fromVolunteer', 'initiator'])
            ->latest()
            ->limit(15)
            ->get();

        return Inertia::render('Handovers/Index', [
            'volunteers' => $volunteers,
            'donors' => $donors,
            'filters' => ['from_volunteer_id' => $fromId],
            'history' => $history,
        ]);
    }

    public function store(Request $request, HandoverService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $validated = $request->validate([
            'from_volunteer_id' => ['required', 'integer', 'exists:users,id'],
            'to_volunteer_ids' => ['required', 'array', 'min:1'],
            'to_volunteer_ids.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', 'in:full,partial'],
            'donor_ids' => ['nullable', 'array'],
            'donor_ids.*' => ['integer', 'exists:donors,id'],
            'reassign_interactions' => ['sometimes', 'boolean'],
            'cap_per_volunteer' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $handover = $service->handover($orgId, $request->user(), $validated);

        return redirect()
            ->route('handovers.index', ['from_volunteer_id' => $validated['from_volunteer_id']])
            ->with('success', "Handover complete: {$handover->donors_moved} donor(s) moved".($handover->interactions_moved ? ", {$handover->interactions_moved} interactions reassigned" : '').'.');
    }
}
