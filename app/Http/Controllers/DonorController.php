<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Http\Requests\Donors\LogCallRequest;
use App\Models\Campaign;
use App\Models\Donor;
use App\Services\Donors\InteractionService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DonorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Donor::class);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $user = $request->user();
        $isVolunteer = $user->isVolunteer();

        // Volunteers default to the "needs call" queue so the next interaction is obvious.
        $filters = $request->only([
            'search', 'assigned_to_me', 'uncontacted', 'follow_up_due',
            'interested', 'do_not_call', 'min_amount', 'donated_after', 'needs_call',
        ]);

        if ($isVolunteer && ! $request->has('needs_call') && ! $request->hasAny([
            'uncontacted', 'follow_up_due', 'interested', 'do_not_call', 'search',
        ])) {
            $filters['needs_call'] = 1;
        }

        $baseQuery = Donor::query()
            ->forOrganization($orgId)
            ->with(['activeAssignment.volunteer']);

        if ($isVolunteer || $request->boolean('assigned_to_me')) {
            $baseQuery->assignedTo($user->id);
        }

        $query = $baseQuery->clone();

        if (! empty($filters['uncontacted'])) {
            $query->uncontacted();
        }

        if (! empty($filters['follow_up_due'])) {
            $query->followUpDue();
        }

        if (! empty($filters['needs_call'])) {
            $query->needsCall();
        }

        if (! empty($filters['interested'])) {
            $query->where('donor_status', DonorStatus::Interested);
        }

        if (! empty($filters['do_not_call'])) {
            $query->where('do_not_call', true);
        }

        if ($request->filled('min_amount')) {
            $query->where('total_donated', '>=', (float) $request->input('min_amount'));
        }

        if ($request->filled('donated_after')) {
            $query->whereDate('last_donation_at', '>=', $request->input('donated_after'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Priority: overdue → due today → upcoming follow-up → never contacted → rest
        $donors = $query
            ->orderByRaw("
                CASE
                    WHEN do_not_call = 1 THEN 9
                    WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at < ? THEN 0
                    WHEN next_follow_up_at IS NOT NULL AND DATE(next_follow_up_at) = DATE(?) THEN 1
                    WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at > ? THEN 2
                    WHEN last_contacted_at IS NULL THEN 3
                    ELSE 4
                END
            ", [now(), now(), now()])
            ->orderBy('next_follow_up_at')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Donor $donor) {
                $donor->setAttribute('call_priority', $donor->callPriority());

                return $donor;
            });

        $queueBase = $baseQuery->clone()->callable();

        $nextToCall = $queueBase->clone()
            ->orderByRaw("
                CASE
                    WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at < ? THEN 0
                    WHEN next_follow_up_at IS NOT NULL AND DATE(next_follow_up_at) = DATE(?) THEN 1
                    WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at > ? THEN 2
                    WHEN last_contacted_at IS NULL THEN 3
                    ELSE 4
                END
            ", [now(), now(), now()])
            ->orderBy('next_follow_up_at')
            ->orderBy('full_name')
            ->first();

        $queueStats = [
            'overdue' => $queueBase->clone()->followUpDue()->where('next_follow_up_at', '<', now()->startOfDay())->count(),
            'due_today' => $queueBase->clone()->followUpToday()->count(),
            'uncontacted' => $queueBase->clone()->uncontacted()->count(),
            'needs_call' => $queueBase->clone()->needsCall()->count(),
        ];

        return Inertia::render('Donors/Index', [
            'donors' => $donors,
            'filters' => $filters,
            'nextToCall' => $nextToCall,
            'queueStats' => $queueStats,
            'isVolunteer' => $isVolunteer,
            'statuses' => collect(DonorStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function show(Request $request, Donor $donor): Response
    {
        $this->authorize('view', $donor);

        $donor->load([
            'organization',
            'donations' => fn ($q) => $q->latest('donated_at'),
            'interactions.volunteer',
            'activeAssignment.volunteer',
        ]);

        $campaigns = Campaign::query()
            ->forOrganization($donor->organization_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $nextDonorId = null;
        if ($request->user()->isVolunteer()) {
            $nextDonorId = Donor::query()
                ->forOrganization($donor->organization_id)
                ->assignedTo($request->user()->id)
                ->callable()
                ->where('id', '!=', $donor->id)
                ->orderByRaw('CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at <= ? THEN 0 ELSE 1 END', [now()])
                ->orderBy('next_follow_up_at')
                ->value('id');
        }

        return Inertia::render('Donors/Show', [
            'donor' => $donor,
            'campaigns' => $campaigns,
            'outcomes' => collect(CallOutcome::cases())->map(fn ($o) => [
                'value' => $o->value,
                'label' => $o->label(),
                'icon' => $o->icon(),
            ]),
            'nextDonorId' => $nextDonorId,
        ]);
    }

    public function logCall(LogCallRequest $request, Donor $donor, InteractionService $service): RedirectResponse
    {
        $this->authorize('view', $donor);

        if ($donor->do_not_call) {
            return back()->with('error', 'This donor is marked Do Not Call.');
        }

        $this->authorize('logCall', $donor);

        $service->logCall($donor, $request->user(), $request->validated());

        if ($request->boolean('go_next')) {
            $nextId = Donor::query()
                ->forOrganization($donor->organization_id)
                ->when($request->user()->isVolunteer(), fn ($q) => $q->assignedTo($request->user()->id))
                ->callable()
                ->where('id', '!=', $donor->id)
                ->orderByRaw('CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at <= ? THEN 0 ELSE 1 END', [now()])
                ->orderBy('next_follow_up_at')
                ->value('id');

            if ($nextId) {
                return redirect()
                    ->route('donors.show', $nextId)
                    ->with('success', 'Call logged. Opening next donor.');
            }
        }

        return back()->with('success', 'Call outcome saved.');
    }

    public function clearDoNotCall(Request $request, Donor $donor): RedirectResponse
    {
        $this->authorize('clearDoNotCall', $donor);

        $donor->update([
            'do_not_call' => false,
            'donor_status' => DonorStatus::FollowUp,
        ]);

        return back()->with('success', 'Do Not Call restriction removed.');
    }
}
