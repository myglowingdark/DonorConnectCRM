<?php

namespace App\Services\Commissions;

use App\Enums\AttributionSource;
use App\Enums\AttributionStatus;
use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\DonationAttribution;
use App\Models\Donor;
use App\Models\User;
use App\Notifications\DonationAttributionNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AttributionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create pending attributions for recent unattributed donations on this donor.
     *
     * @return list<DonationAttribution>
     */
    public function queueFromCall(Donor $donor, User $volunteer): array
    {
        $donations = Donation::query()
            ->forOrganization($donor->organization_id)
            ->where('donor_id', $donor->id)
            ->where('donated_at', '>=', now()->subDays(30))
            ->whereDoesntHave('attribution')
            ->orderBy('donated_at')
            ->get();

        $created = [];

        foreach ($donations as $donation) {
            $attribution = DonationAttribution::create([
                'organization_id' => $donor->organization_id,
                'donation_id' => $donation->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'source' => AttributionSource::Call,
                'status' => AttributionStatus::Pending,
            ]);
            $created[] = $attribution->load(['donor', 'volunteer', 'donation']);
        }

        if ($created !== []) {
        $admins = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::OrganizationAdmin)
            ->whereHas(
                'organizations',
                fn ($q) => $q->where('organizations.id', $donor->organization_id)
                    ->where('organization_user.is_active', true)
            )
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new DonationAttributionNotification($created[0], 'queued', count($created))
            );
        }

            $this->auditLogger->log(
                'donation.attribution_queued',
                $donor,
                null,
                [
                    'count' => count($created),
                    'volunteer_id' => $volunteer->id,
                    'attribution_ids' => collect($created)->pluck('id')->all(),
                ],
                $donor->organization_id,
                $volunteer,
            );
        }

        return $created;
    }

    public function approve(DonationAttribution $attribution, User $actor, ?string $note = null): DonationAttribution
    {
        abort_unless($actor->isAdmin(), 403);

        if ($attribution->status !== AttributionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending attributions can be approved.',
            ]);
        }

        $attribution->update([
            'status' => AttributionStatus::Approved,
            'admin_note' => $note,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        if ($attribution->volunteer) {
            $attribution->volunteer->notify(
                new DonationAttributionNotification($attribution, 'approved')
            );
        }

        return $attribution->fresh();
    }

    public function reject(DonationAttribution $attribution, User $actor, ?string $note = null): DonationAttribution
    {
        abort_unless($actor->isAdmin(), 403);

        if ($attribution->status !== AttributionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending attributions can be rejected.',
            ]);
        }

        $attribution->update([
            'status' => AttributionStatus::Rejected,
            'admin_note' => $note,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        if ($attribution->volunteer) {
            $attribution->volunteer->notify(
                new DonationAttributionNotification($attribution, 'rejected')
            );
        }

        return $attribution->fresh();
    }
}
