<?php

namespace App\Http\Controllers;

use App\Models\OrganizationWebhook;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $webhooks = OrganizationWebhook::query()
            ->forOrganization($orgId)
            ->withCount('deliveries')
            ->latest()
            ->get();

        return Inertia::render('Api/Webhooks', [
            'webhooks' => $webhooks,
            'availableEvents' => ['donation.created', 'lead.assigned', 'pledge.made'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:donation.created,lead.assigned,pledge.made'],
            'secret' => ['nullable', 'string', 'max:255'],
        ]);

        OrganizationWebhook::create([
            'organization_id' => $orgId,
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $validated['secret'] ?? Str::random(32),
            'is_active' => true,
        ]);

        return back()->with('success', 'Webhook created.');
    }

    public function update(Request $request, OrganizationWebhook $webhook): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($webhook->organization_id === OrganizationContext::id(), 403);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:donation.created,lead.assigned,pledge.made'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($validated);

        return back()->with('success', 'Webhook updated.');
    }

    public function destroy(Request $request, OrganizationWebhook $webhook): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($webhook->organization_id === OrganizationContext::id(), 403);

        $webhook->delete();

        return back()->with('success', 'Webhook deleted.');
    }
}
