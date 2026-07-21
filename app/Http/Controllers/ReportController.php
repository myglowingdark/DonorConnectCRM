<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $orgId = $request->integer('organization_id') ?: OrganizationContext::id();

        if ($user->isOrganizationAdmin()) {
            $orgId = OrganizationContext::id();
        }

        abort_unless($orgId && $user->belongsToOrganization($orgId), 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        $interactions = DonorInteraction::query()
            ->forOrganization($orgId)
            ->whereBetween('contacted_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($request->filled('volunteer_id'), fn ($q) => $q->where('volunteer_id', $request->integer('volunteer_id')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')));

        $outcomeBreakdown = (clone $interactions)
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $team = User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->where('role', 'volunteer')
            ->withCount([
                'interactions as calls_count' => fn ($q) => $q->where('organization_id', $orgId)
                    ->whereBetween('contacted_at', [$from, $to]),
            ])
            ->orderBy('name')
            ->get();

        $donationTotal = Donation::query()
            ->forOrganization($orgId)
            ->whereBetween('donated_at', [$from, $to])
            ->sum('amount');

        $callsTotal = (clone $interactions)->count();
        $conversions = (clone $interactions)->whereIn('outcome', ['pledged', 'donated'])->count();

        return Inertia::render('Reports/Index', [
            'filters' => [
                'organization_id' => $orgId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'volunteer_id' => $request->input('volunteer_id'),
                'outcome' => $request->input('outcome'),
                'tab' => $request->input('tab', 'team'),
            ],
            'cards' => [
                'total_collection' => $donationTotal,
                'calls_total' => $callsTotal,
                'conversion_rate' => $callsTotal > 0 ? round(($conversions / $callsTotal) * 100, 1) : 0,
                'follow_ups_due' => Donor::query()->forOrganization($orgId)->followUpDue()->count(),
            ],
            'outcomeBreakdown' => $outcomeBreakdown,
            'team' => $team,
            'volunteers' => User::query()
                ->where('role', 'volunteer')
                ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'organizations' => $user->isSuperAdmin()
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'isConsolidated' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && $user->belongsToOrganization($orgId), 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $filename = 'donorconnect-report-'.$orgId.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($orgId, $request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Donor', 'Phone', 'Outcome', 'Volunteer', 'Contacted At', 'Follow Up', 'Notes']);

            DonorInteraction::query()
                ->forOrganization($orgId)
                ->with(['donor', 'volunteer'])
                ->when($request->filled('from'), fn ($q) => $q->whereDate('contacted_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('contacted_at', '<=', $request->input('to')))
                ->orderByDesc('contacted_at')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->donor?->full_name,
                            $row->donor?->phone,
                            $row->outcome?->value,
                            $row->volunteer?->name,
                            $row->contacted_at?->toDateTimeString(),
                            $row->follow_up_at?->toDateTimeString(),
                            $row->notes,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
