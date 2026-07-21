<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $orgId = OrganizationContext::id();

        if ($user->isVolunteer()) {
            return $this->volunteerDashboard($user, $orgId);
        }

        if ($user->isOrganizationAdmin()) {
            return $this->orgAdminDashboard($user, $orgId);
        }

        return $this->superAdminDashboard($user, $orgId);
    }

    protected function volunteerDashboard(User $user, ?int $orgId): Response
    {
        abort_unless($orgId, 403);

        $assignedQuery = Donor::query()
            ->forOrganization($orgId)
            ->assignedTo($user->id);

        $assignedCount = (clone $assignedQuery)->count();
        $callsToday = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('volunteer_id', $user->id)
            ->whereDate('contacted_at', today())
            ->count();

        $followUpsDue = (clone $assignedQuery)->followUpDue()->count();

        $nextDonor = (clone $assignedQuery)
            ->callable()
            ->orderForNextCall()
            ->first();

        $followUps = (clone $assignedQuery)
            ->followUpDue()
            ->with('activeAssignment.volunteer')
            ->orderBy('next_follow_up_at')
            ->limit(8)
            ->get();

        $recent = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('volunteer_id', $user->id)
            ->with('donor')
            ->latest('contacted_at')
            ->limit(8)
            ->get();

        $weeklyCalls = $this->dailyCallSeries(
            DonorInteraction::query()
                ->forOrganization($orgId)
                ->where('volunteer_id', $user->id)
        );

        $outcomeSeries = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('volunteer_id', $user->id)
            ->where('contacted_at', '>=', now()->subDays(14))
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->get()
            ->map(fn ($row) => [
                'label' => str_replace('_', ' ', $row->outcome instanceof \BackedEnum ? $row->outcome->value : (string) $row->outcome),
                'value' => (int) $row->total,
            ])
            ->values();

        return Inertia::render('Volunteer/Dashboard', [
            'stats' => [
                'assigned_donors' => $assignedCount,
                'calls_today' => $callsToday,
                'follow_ups_due' => $followUpsDue,
                'verified_donations_month' => 0,
                'estimated_individual_commission' => null,
                'estimated_shared_commission' => null,
            ],
            'nextDonor' => $nextDonor,
            'followUps' => $followUps,
            'recentActivity' => $recent,
            'weeklyCalls' => $weeklyCalls,
            'outcomeSeries' => $outcomeSeries,
            'phase2Notice' => null,
        ]);
    }

    protected function orgAdminDashboard(User $user, ?int $orgId): Response
    {
        abort_unless($orgId && $user->belongsToOrganization($orgId), 403);

        $org = Organization::query()->with('apiConnection')->findOrFail($orgId);

        $activeVolunteers = $org->users()
            ->where('role', 'volunteer')
            ->wherePivot('is_active', true)
            ->where('users.is_active', true)
            ->count();

        $callsThisWeek = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('contacted_at', '>=', now()->startOfWeek())
            ->count();

        $followUpsDue = Donor::query()->forOrganization($orgId)->followUpDue()->count();

        $donationTotal = Donation::query()
            ->forOrganization($orgId)
            ->where('donated_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $team = User::query()
            ->where('role', 'volunteer')
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->withCount([
                'interactions as calls_count' => fn ($q) => $q->where('organization_id', $orgId)
                    ->where('contacted_at', '>=', now()->startOfMonth()),
                'assignments as donor_count' => fn ($q) => $q->where('organization_id', $orgId)->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();

        $outcomes = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('contacted_at', '>=', now()->subDays(14))
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $weeklyCalls = $this->dailyCallSeries(
            DonorInteraction::query()->forOrganization($orgId)
        );

        $teamCallSeries = $team->map(fn (User $v) => [
            'label' => $v->name,
            'value' => (int) $v->calls_count,
        ])->values();

        $pendingTransfers = \App\Models\DonorTransferRequest::query()
            ->forOrganization($orgId)
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'organization' => $org,
            'stats' => [
                'telecalling_donations' => $donationTotal,
                'active_volunteers' => $activeVolunteers,
                'calls_this_week' => $callsThisWeek,
                'follow_ups_due' => $followUpsDue,
                'estimated_commission' => null,
                'sync_status' => $org->apiConnection?->sync_status?->value ?? 'idle',
            ],
            'team' => $team,
            'outcomes' => $outcomes,
            'weeklyCalls' => $weeklyCalls,
            'teamCallSeries' => $teamCallSeries,
            'pendingActions' => [
                'overdue_follow_ups' => $followUpsDue,
                'sync_errors' => $org->apiConnection?->last_error ? 1 : 0,
                'pending_transfers' => $pendingTransfers,
            ],
            'phase2Notice' => null,
        ]);
    }

    protected function superAdminDashboard(User $user, ?int $orgId): Response
    {
        $organizations = Organization::query()
            ->withCount(['donors', 'users'])
            ->with('apiConnection')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'brand_color' => $org->brand_color,
                    'initials' => $org->initials(),
                    'is_active' => $org->is_active,
                    'donors_count' => $org->donors_count,
                    'donors_limit' => $org->donors_limit,
                    'users_count' => $org->users_count,
                    'sync_status' => $org->apiConnection?->sync_status?->value ?? 'idle',
                    'monthly_collection' => Donation::query()
                        ->forOrganization($org->id)
                        ->where('donated_at', '>=', now()->startOfMonth())
                        ->sum('amount'),
                ];
            });

        return Inertia::render('SuperAdmin/Dashboard', [
            'organizations' => $organizations,
            'stats' => [
                'organizations' => Organization::count(),
                'users' => User::count(),
                'donors' => Donor::count(),
                'donations_month' => Donation::query()->where('donated_at', '>=', now()->startOfMonth())->sum('amount'),
            ],
            ]);
    }

    /**
     * Fill a 7-day call series (oldest → newest) for charts.
     *
     * @return list<array{label: string, value: int}>
     */
    protected function dailyCallSeries($query): array
    {
        $counts = (clone $query)
            ->where('contacted_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(contacted_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->toDateString();
            $series[] = [
                'label' => $day->format('D'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $series;
    }
}
