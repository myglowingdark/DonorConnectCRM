<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Enums\MessageChannel;
use App\Enums\TransferStatus;
use App\Http\Requests\Donors\LogCallRequest;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\DonorTransferRequest;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Donors\InteractionService;
use App\Services\Messaging\MessageService;
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
                ->callable()
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
        $enabledChannels = collect(MessageChannel::cases())
            ->filter(fn (MessageChannel $channel) => match ($channel) {
                MessageChannel::Email => $messagingSettings->email_enabled,
                MessageChannel::WhatsApp => $messagingSettings->whatsapp_enabled,
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
            ->orderBy('name')
            ->get(['id', 'name', 'channel', 'subject', 'body']);

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
                ->callable()
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
