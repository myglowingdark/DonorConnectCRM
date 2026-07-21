<?php

namespace App\Http\Controllers;

use App\Models\CommissionCycle;
use App\Models\CommissionLineItem;
use App\Models\Organization;
use App\Services\Commissions\CommissionService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionCycleController extends Controller
{
    public function index(Request $request, CommissionService $service): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $cycles = CommissionCycle::query()
            ->forOrganization($orgId)
            ->withCount('lineItems')
            ->orderByDesc('period')
            ->paginate(12);

        return Inertia::render('Commissions/Cycles', [
            'cycles' => $cycles,
            'defaultPeriod' => now()->format('Y-m'),
            'settings' => $service->settingsFor($orgId)->only([
                'individual_enabled',
                'individual_default_percent',
                'shared_enabled',
                'shared_percent',
            ]),
        ]);
    }

    public function calculate(Request $request, CommissionService $service): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $cycle = $service->calculate($orgId, $data['period'], $request->user());

        return redirect()
            ->route('commissions.cycles.show', $cycle)
            ->with('success', "Cycle {$cycle->period} calculated.");
    }

    public function show(Request $request, CommissionCycle $cycle): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && (int) $cycle->organization_id === (int) $orgId, 403);

        $cycle->load(['lineItems.volunteer:id,name,email']);

        return Inertia::render('Commissions/CycleShow', [
            'cycle' => $cycle,
        ]);
    }

    public function approve(Request $request, CommissionCycle $cycle, CommissionService $service): RedirectResponse
    {
        $this->guardCycle($request, $cycle);
        $service->approve($cycle, $request->user());

        return back()->with('success', 'Cycle approved.');
    }

    public function markPaid(Request $request, CommissionCycle $cycle, CommissionService $service): RedirectResponse
    {
        $this->guardCycle($request, $cycle);
        $service->markPaid($cycle, $request->user());

        return back()->with('success', 'Cycle marked as paid.');
    }

    public function mine(Request $request): Response
    {
        $user = $request->user();
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $items = CommissionLineItem::query()
            ->forOrganization($orgId)
            ->where('volunteer_id', $user->id)
            ->with('cycle')
            ->latest()
            ->paginate(20);

        $totals = [
            'payable' => (float) CommissionLineItem::query()
                ->forOrganization($orgId)
                ->where('volunteer_id', $user->id)
                ->sum('final_payable'),
            'paid' => (float) CommissionLineItem::query()
                ->forOrganization($orgId)
                ->where('volunteer_id', $user->id)
                ->where('status', 'paid')
                ->sum('final_payable'),
        ];

        return Inertia::render('Commissions/Mine', [
            'lineItems' => $items,
            'totals' => $totals,
        ]);
    }

    protected function guardCycle(Request $request, CommissionCycle $cycle): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && (int) $cycle->organization_id === (int) $orgId, 403);
    }
}
