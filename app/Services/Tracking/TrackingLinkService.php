<?php

namespace App\Services\Tracking;

use App\Enums\AttributionSource;
use App\Enums\AttributionStatus;
use App\Enums\TrackingEventType;
use App\Enums\TransferStatus;
use App\Models\Donation;
use App\Models\DonationAttribution;
use App\Models\Donor;
use App\Models\DonorTransferRequest;
use App\Models\Organization;
use App\Models\TrackingEvent;
use App\Models\TrackingLink;
use App\Models\User;
use App\Notifications\DonationAttributionNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingLinkService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * One reusable link per donor + volunteer. Resend keeps the same code.
     */
    public function resolveOrCreate(Donor $donor, User $volunteer, string $targetUrl, ?string $channel = null): TrackingLink
    {
        $targetUrl = $this->normalizeTargetUrl($targetUrl);

        $link = TrackingLink::query()
            ->forOrganization($donor->organization_id)
            ->where('donor_id', $donor->id)
            ->where('volunteer_id', $volunteer->id)
            ->first();

        if ($link) {
            $link->update([
                'target_url' => $targetUrl,
                'channel' => $channel ?? $link->channel,
            ]);

            return $link->fresh();
        }

        return TrackingLink::create([
            'organization_id' => $donor->organization_id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'code' => $this->uniqueCode(),
            'target_url' => $targetUrl,
            'channel' => $channel,
        ]);
    }

    public function markSent(TrackingLink $link, ?int $outboundMessageId = null, ?string $channel = null): TrackingEvent
    {
        $link->update([
            'last_sent_at' => now(),
            'outbound_message_id' => $outboundMessageId ?? $link->outbound_message_id,
            'channel' => $channel ?? $link->channel,
        ]);

        return $this->recordEvent($link, TrackingEventType::Sent, [
            'outbound_message_id' => $outboundMessageId,
            'channel' => $channel ?? $link->channel,
        ]);
    }

    public function recordOpen(TrackingLink $link, ?string $pageUrl = null): TrackingEvent
    {
        $link->update([
            'open_count' => $link->open_count + 1,
            'last_opened_at' => now(),
        ]);

        return $this->recordEvent($link, TrackingEventType::Opened, pageUrl: $pageUrl);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordEvent(
        TrackingLink $link,
        TrackingEventType $type,
        array $meta = [],
        ?string $pageUrl = null,
        ?string $projectId = null,
        ?float $amount = null,
    ): TrackingEvent {
        return TrackingEvent::create([
            'organization_id' => $link->organization_id,
            'tracking_link_id' => $link->id,
            'volunteer_id' => $link->volunteer_id,
            'event_type' => $type,
            'page_url' => $pageUrl,
            'project_id' => $projectId,
            'amount' => $amount,
            'meta' => $meta === [] ? null : $meta,
            'occurred_at' => now(),
        ]);
    }

    public function redirectTarget(TrackingLink $link): string
    {
        $url = $link->target_url;
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'dcr='.urlencode($link->code);
    }

    /**
     * @return Collection<int, TrackingLink>
     */
    public function visibleLinksFor(Donor $donor, User $viewer): Collection
    {
        $query = TrackingLink::query()
            ->forOrganization($donor->organization_id)
            ->where('donor_id', $donor->id)
            ->with(['volunteer:id,name', 'events' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id')]);

        if ($viewer->isSuperAdmin() || $viewer->isOrganizationAdmin()) {
            return $query->orderByDesc('updated_at')->get();
        }

        $volunteerIds = [$viewer->id];

        if ($this->viewerReceivedTransfer($donor, $viewer)) {
            // Transfer recipient can see all link activity on this donor.
            return $query->orderByDesc('updated_at')->get();
        }

        return $query->where('volunteer_id', $viewer->id)->orderByDesc('updated_at')->get();
    }

    public function canViewLink(TrackingLink $link, User $viewer): bool
    {
        if ($viewer->isSuperAdmin() || $viewer->isOrganizationAdmin()) {
            return true;
        }

        if ($link->volunteer_id === $viewer->id) {
            return true;
        }

        $donor = $link->donor ?? Donor::query()->find($link->donor_id);

        return $donor ? $this->viewerReceivedTransfer($donor, $viewer) : false;
    }

    public function attributeDonationIfTracked(Donation $donation): ?DonationAttribution
    {
        if ($donation->attribution()->exists()) {
            return null;
        }

        $code = $this->extractDcrCode($donation->source_data ?? []);
        if ($code === null) {
            return null;
        }

        $link = TrackingLink::query()
            ->forOrganization($donation->organization_id)
            ->where('code', $code)
            ->first();

        if (! $link) {
            return null;
        }

        if ((int) $link->donor_id !== (int) $donation->donor_id) {
            return null;
        }

        $organization = Organization::query()->find($donation->organization_id);
        $windowDays = max(1, (int) ($organization?->attribution_window_days ?? 3));

        $lastOpen = $link->last_opened_at;
        if (! $lastOpen || $lastOpen->lt(now()->subDays($windowDays))) {
            return null;
        }

        $attribution = DonationAttribution::create([
            'organization_id' => $donation->organization_id,
            'donation_id' => $donation->id,
            'donor_id' => $donation->donor_id,
            'volunteer_id' => $link->volunteer_id,
            'source' => AttributionSource::TrackingLink,
            'tracking_link_id' => $link->id,
            'status' => AttributionStatus::Approved,
            'admin_note' => 'Auto-approved from tracking link (dcr).',
            'reviewed_at' => now(),
        ]);

        $this->recordEvent($link, TrackingEventType::Paid, [
            'donation_id' => $donation->id,
            'external_donation_id' => $donation->external_donation_id,
        ], amount: (float) $donation->amount);

        if ($attribution->volunteer) {
            $attribution->volunteer->notify(
                new DonationAttributionNotification($attribution, 'link_paid')
            );
        }

        $this->auditLogger->log(
            'donation.attribution_auto_approved',
            $donation,
            null,
            [
                'attribution_id' => $attribution->id,
                'tracking_link_id' => $link->id,
                'volunteer_id' => $link->volunteer_id,
                'code' => $link->code,
            ],
            $donation->organization_id,
        );

        return $attribution->load(['donor', 'volunteer', 'donation']);
    }

    /**
     * @param  array<string, mixed>  $sourceData
     */
    public function extractDcrCode(array $sourceData): ?string
    {
        $candidates = [
            data_get($sourceData, 'dcr'),
            data_get($sourceData, 'source_data.dcr'),
            data_get($sourceData, 'utm_content'),
            data_get($sourceData, 'source_data.utm_content'),
        ];

        foreach ($candidates as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (str_starts_with(strtolower($value), 'dcr:')) {
                $value = substr($value, 4);
            }

            if (preg_match('/^[A-Za-z0-9_-]{6,32}$/', $value)) {
                return $value;
            }
        }

        return null;
    }

    protected function viewerReceivedTransfer(Donor $donor, User $viewer): bool
    {
        return DonorTransferRequest::query()
            ->where('donor_id', $donor->id)
            ->where('to_volunteer_id', $viewer->id)
            ->where('status', TransferStatus::Accepted)
            ->exists();
    }

    protected function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while (TrackingLink::query()->where('code', $code)->exists());

        return $code;
    }

    protected function normalizeTargetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'target_url' => 'A valid NGOBuddy project URL is required.',
            ]);
        }

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'target_url' => 'Project URL must start with http:// or https://.',
            ]);
        }

        return $url;
    }
}
