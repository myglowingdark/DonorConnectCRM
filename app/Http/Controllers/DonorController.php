<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Enums\MessageChannel;
use App\Enums\MetaTemplateStatus;
use App\Enums\TransferStatus;
use App\Http\Requests\Donors\LogCallRequest;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\DonorTransferRequest;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\InteractionService;
use App\Services\Messaging\MessageService;
use App\Services\SaaS\EntitlementService;
use App\Support\Languages;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'interested', 'do_not_call', 'min_amount', 'max_amount',
            'donated_after', 'donated_before', 'last_called_after', 'last_called_before',
            'last_called_by', 'campaign_id', 'tag', 'was_transferred', 'needs_call',
        ]);

        if ($isVolunteer && ! $request->has('needs_call') && ! $request->hasAny([
            'uncontacted', 'follow_up_due', 'interested', 'do_not_call', 'search',
            'min_amount', 'max_amount', 'donated_after', 'donated_before',
            'last_called_after', 'last_called_before', 'last_called_by', 'campaign_id', 'tag',
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

        if (! empty($filters['was_transferred'])) {
            $query->where('was_transferred', true);
        }

        if ($request->filled('min_amount')) {
            $query->where('total_donated', '>=', (float) $request->input('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('total_donated', '<=', (float) $request->input('max_amount'));
        }

        if ($request->filled('donated_after')) {
            $query->whereDate('last_donation_at', '>=', $request->input('donated_after'));
        }

        if ($request->filled('donated_before')) {
            $query->whereDate('last_donation_at', '<=', $request->input('donated_before'));
        }

        if ($request->filled('last_called_after')) {
            $query->whereDate('last_contacted_at', '>=', $request->input('last_called_after'));
        }

        if ($request->filled('last_called_before')) {
            $query->whereDate('last_contacted_at', '<=', $request->input('last_called_before'));
        }

        if ($request->filled('last_called_by')) {
            $volunteerId = (int) $request->input('last_called_by');
            $latestIds = DonorInteraction::query()
                ->forOrganization($orgId)
                ->selectRaw('MAX(id) as id')
                ->groupBy('donor_id');

            $query->whereIn('id', DonorInteraction::query()
                ->whereIn('id', $latestIds)
                ->where('volunteer_id', $volunteerId)
                ->select('donor_id'));
        }

        if ($request->filled('campaign_id')) {
            $campaignId = (int) $request->input('campaign_id');
            $query->where(function ($q) use ($campaignId) {
                $q->where('campaign_id', $campaignId)
                    ->orWhereHas('donations', fn ($d) => $d->where('campaign_id', $campaignId));
            });
        }

        if ($request->filled('tag')) {
            $query->withTag($request->string('tag')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Priority: due follow-ups → upcoming follow-ups → no follow-up (longest since last call)
        $donors = $query
            ->orderForNextCall()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Donor $donor) {
                $donor->setAttribute('call_priority', $donor->callPriority());

                return $donor;
            });

        $queueBase = $baseQuery->clone()->callable();

        $nextToCall = $queueBase->clone()
            ->orderForNextCall()
            ->first();

        $queueStats = [
            'overdue' => $queueBase->clone()->followUpDue()->where('next_follow_up_at', '<', now()->startOfDay())->count(),
            'due_today' => $queueBase->clone()->followUpToday()->count(),
            'uncontacted' => $queueBase->clone()->uncontacted()->count(),
            'needs_call' => $queueBase->clone()->needsCall()->count(),
        ];

        $availableTags = Donor::query()
            ->forOrganization($orgId)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

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
            'campaigns' => Campaign::query()
                ->forOrganization($orgId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'volunteers' => User::query()
                ->where('role', 'volunteer')
                ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'availableTags' => $availableTags,
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
            'pendingTransfer.toVolunteer',
            'transferRequests.fromVolunteer',
            'transferRequests.toVolunteer',
            'transferRequests.requester',
            'transferRequests.responder',
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
                ->needsCall()
                ->where('id', '!=', $donor->id)
                ->orderForNextCall()
                ->value('id');
        }

        $transferVolunteers = User::query()
            ->where('role', 'volunteer')
            ->where('is_active', true)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $donor->organization_id))
            ->when(
                $request->user()->isVolunteer(),
                fn ($q) => $q->where('id', '!=', $request->user()->id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'languages']);

        $canTransfer = $request->user()->isAdmin()
            || $donor->activeAssignment?->volunteer_id === $request->user()->id;

        $messagingSettings = app(MessageService::class)->settingsFor($donor->organization_id);
        $organization = Organization::query()->findOrFail($donor->organization_id);
        $hasWhatsApp = app(EntitlementService::class)->hasFeature($organization, 'whatsapp');

        $enabledChannels = collect(MessageChannel::cases())
            ->filter(fn (MessageChannel $channel) => match ($channel) {
                MessageChannel::Email => $messagingSettings->email_enabled,
                MessageChannel::WhatsApp => $messagingSettings->whatsapp_enabled && $hasWhatsApp,
                MessageChannel::Sms => $messagingSettings->sms_enabled,
            })
            ->map(fn (MessageChannel $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])
            ->values();

        $messageTemplates = MessageTemplate::query()
            ->forOrganization($donor->organization_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('channel', '!=', MessageChannel::WhatsApp->value)
                    ->orWhere(function ($whatsapp) {
                        $whatsapp->where('channel', MessageChannel::WhatsApp->value)
                            ->where('meta_status', MetaTemplateStatus::Approved->value);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'channel', 'subject', 'body', 'meta_status', 'meta_name', 'meta_language']);

        $trackingLinks = app(\App\Services\Tracking\TrackingLinkService::class)
            ->visibleLinksFor($donor, $request->user())
            ->map(function ($link) {
                return [
                    'id' => $link->id,
                    'code' => $link->code,
                    'url' => $link->publicUrl(),
                    'target_url' => $link->target_url,
                    'channel' => $link->channel,
                    'open_count' => $link->open_count,
                    'last_opened_at' => optional($link->last_opened_at)?->toIso8601String(),
                    'last_sent_at' => optional($link->last_sent_at)?->toIso8601String(),
                    'volunteer' => $link->volunteer ? [
                        'id' => $link->volunteer->id,
                        'name' => $link->volunteer->name,
                    ] : null,
                    'events' => $link->events->map(fn ($event) => [
                        'id' => $event->id,
                        'event_type' => $event->event_type instanceof \BackedEnum ? $event->event_type->value : $event->event_type,
                        'page_url' => $event->page_url,
                        'project_id' => $event->project_id,
                        'amount' => $event->amount,
                        'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
                    ])->values(),
                ];
            })
            ->values();

        return Inertia::render('Donors/Show', [
            'donor' => $donor,
            'timeline' => $this->buildTimeline($donor),
            'campaigns' => $campaigns,
            'outcomes' => collect(CallOutcome::cases())->map(fn ($o) => [
                'value' => $o->value,
                'label' => $o->label(),
                'icon' => $o->icon(),
            ]),
            'nextDonorId' => $nextDonorId,
            'languages' => Languages::forSelect(),
            'transferVolunteers' => $transferVolunteers,
            'canTransfer' => $canTransfer,
            'messagingChannels' => $enabledChannels,
            'messageTemplates' => $messageTemplates,
            'hasWhatsAppFeature' => $hasWhatsApp,
            'trackingLinks' => $trackingLinks,
            'attributionWindowDays' => (int) ($organization->attribution_window_days ?: 3),
        ]);
    }

    /**
     * Merge call interactions and transfer history into one chronological timeline.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildTimeline(Donor $donor): array
    {
        $calls = $donor->interactions->map(function (DonorInteraction $item) {
            return [
                'id' => 'call-'.$item->id,
                'type' => 'call',
                'at' => optional($item->contacted_at)?->toIso8601String(),
                'title' => str_replace('_', ' ', $item->outcome instanceof \BackedEnum ? $item->outcome->value : (string) $item->outcome),
                'actor' => $item->volunteer?->name,
                'notes' => $item->notes,
                'follow_up_at' => optional($item->follow_up_at)?->toIso8601String(),
                'status' => null,
                'from' => null,
                'to' => null,
                'reason' => null,
                'response_note' => null,
            ];
        });

        $transfers = $donor->transferRequests->flatMap(function (DonorTransferRequest $transfer) {
            $from = $transfer->fromVolunteer?->name ?? 'Volunteer';
            $to = $transfer->toVolunteer?->name ?? 'Volunteer';
            $status = $transfer->status instanceof TransferStatus
                ? $transfer->status
                : TransferStatus::from((string) $transfer->status);

            $entries = Collection::make([
                [
                    'id' => 'transfer-request-'.$transfer->id,
                    'type' => 'transfer',
                    'at' => optional($transfer->created_at)?->toIso8601String(),
                    'title' => 'Transfer requested',
                    'actor' => $transfer->requester?->name ?? $from,
                    'notes' => null,
                    'follow_up_at' => null,
                    'status' => TransferStatus::Pending->value,
                    'from' => $from,
                    'to' => $to,
                    'reason' => $transfer->reason,
                    'response_note' => null,
                ],
            ]);

            if ($status !== TransferStatus::Pending && $transfer->responded_at) {
                $entries->push([
                    'id' => 'transfer-response-'.$transfer->id,
                    'type' => 'transfer',
                    'at' => optional($transfer->responded_at)?->toIso8601String(),
                    'title' => 'Transfer '.$status->label(),
                    'actor' => $transfer->responder?->name ?? $to,
                    'notes' => null,
                    'follow_up_at' => null,
                    'status' => $status->value,
                    'from' => $from,
                    'to' => $to,
                    'reason' => $transfer->reason,
                    'response_note' => $transfer->response_note,
                ]);
            }

            return $entries;
        });

        return $calls
            ->concat($transfers)
            ->sortByDesc(fn (array $item) => $item['at'] ?? '')
            ->values()
            ->all();
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
                ->needsCall()
                ->where('id', '!=', $donor->id)
                ->orderForNextCall()
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
