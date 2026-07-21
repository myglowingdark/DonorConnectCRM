<?php

namespace App\Http\Controllers;

use App\Models\CallQualityRating;
use App\Models\CommissionHold;
use App\Models\DonorInteraction;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CallQualityController extends Controller
{
    public function store(Request $request, DonorInteraction $interaction): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId && $interaction->organization_id === $orgId, 403);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        CallQualityRating::updateOrCreate(
            [
                'interaction_id' => $interaction->id,
                'rated_by' => $request->user()->id,
            ],
            [
                'organization_id' => $orgId,
                'volunteer_id' => $interaction->volunteer_id,
                'score' => $validated['score'],
                'comment' => $validated['comment'] ?? null,
            ],
        );

        return back()->with('success', 'Call quality rating saved.');
    }

    public function createHold(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'volunteer_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'commission_line_item_id' => ['nullable', 'integer', 'exists:commission_line_items,id'],
        ]);

        CommissionHold::create([
            'organization_id' => $orgId,
            'volunteer_id' => $validated['volunteer_id'],
            'commission_line_item_id' => $validated['commission_line_item_id'] ?? null,
            'created_by' => $request->user()->id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'held',
        ]);

        return back()->with('success', 'Commission hold created.');
    }

    public function releaseHold(Request $request, CommissionHold $hold): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($hold->organization_id === OrganizationContext::id(), 403);

        $hold->update(['status' => 'released']);

        return back()->with('success', 'Commission hold released.');
    }
}
