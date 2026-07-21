<?php

namespace App\Http\Controllers;

use App\Http\Requests\Assignments\AssignDonorsRequest;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\AssignmentService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request, AssignmentService $assignmentService): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $this->authorize('assignDonors', $organization);

        $unassigned = Donor::query()
            ->forOrganization($orgId)
            ->whereDoesntHave('assignments', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->limit(100)
            ->get();

        $volunteers = User::query()
            ->where('role', 'volunteer')
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($q) => $q->where('organizations.id', $orgId)->where('organization_user.is_active', true)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        $selectedVolunteerId = $request->integer('volunteer_id') ?: $volunteers->first()?->id;

        $assigned = collect();
        if ($selectedVolunteerId) {
            $assigned = Donor::query()
                ->forOrganization($orgId)
                ->assignedTo($selectedVolunteerId)
                ->orderBy('full_name')
                ->get();
        }

        $workload = $assignmentService->workloadCounts($orgId)
            ->mapWithKeys(fn ($count, $volunteerId) => [(string) $volunteerId => (int) $count]);

        return Inertia::render('Assignments/Index', [
            'unassigned' => $unassigned,
            'assigned' => $assigned,
            'volunteers' => $volunteers,
            'selectedVolunteerId' => $selectedVolunteerId ? (int) $selectedVolunteerId : null,
            'workload' => $workload,
            'filters' => [
                'search' => $request->input('search'),
                'volunteer_id' => $selectedVolunteerId ? (int) $selectedVolunteerId : null,
            ],
        ]);
    }

    public function store(AssignDonorsRequest $request, AssignmentService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $count = $service->assignDonors(
            $orgId,
            (int) $request->integer('volunteer_id'),
            array_map('intval', $request->input('donor_ids', [])),
            $request->user(),
        );

        return redirect()
            ->route('assignments.index', ['volunteer_id' => $request->integer('volunteer_id')])
            ->with('success', "{$count} donor(s) assigned.");
    }

    public function destroy(Request $request, AssignmentService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $validated = $request->validate([
            'donor_ids' => ['required', 'array', 'min:1'],
            'donor_ids.*' => ['integer', 'exists:donors,id'],
            'volunteer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $count = $service->unassignDonors(
            $orgId,
            array_map('intval', $validated['donor_ids']),
            $request->user(),
        );

        return redirect()
            ->route('assignments.index', [
                'volunteer_id' => $validated['volunteer_id'] ?? null,
            ])
            ->with('success', "{$count} donor(s) unassigned.");
    }

    public function distribute(Request $request, AssignmentService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $validated = $request->validate([
            'volunteer_ids' => ['required', 'array', 'min:1'],
            'volunteer_ids.*' => ['integer', 'exists:users,id'],
            'cap_per_volunteer' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $count = $service->distributeEqually(
            $orgId,
            array_map('intval', $validated['volunteer_ids']),
            $request->user(),
            isset($validated['cap_per_volunteer']) ? (int) $validated['cap_per_volunteer'] : null,
        );

        return redirect()
            ->route('assignments.index')
            ->with('success', "Distributed {$count} unassigned donor(s) equally".(isset($validated['cap_per_volunteer']) ? " (cap {$validated['cap_per_volunteer']}/volunteer)" : '').'.');
    }
}
