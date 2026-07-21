<?php

namespace App\Services\Donors;

use App\Enums\TransferStatus;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorTransferRequest;
use App\Models\User;
use App\Notifications\DonorTransferNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(
        private AssignmentService $assignmentService,
        private AuditLogger $auditLogger,
    ) {}

    public function request(
        Donor $donor,
        User $fromVolunteer,
        User $toVolunteer,
        User $actor,
        ?string $reason = null,
    ): DonorTransferRequest {
        if ($fromVolunteer->id === $toVolunteer->id) {
            throw ValidationException::withMessages([
                'to_volunteer_id' => 'Choose a different volunteer.',
            ]);
        }

        if (! $toVolunteer->isVolunteer() || ! $toVolunteer->is_active) {
            throw ValidationException::withMessages([
                'to_volunteer_id' => 'Recipient must be an active volunteer.',
            ]);
        }

        if (! $toVolunteer->belongsToOrganization($donor->organization_id)) {
            throw ValidationException::withMessages([
                'to_volunteer_id' => 'Recipient is not in this organization.',
            ]);
        }

        $ownsDonor = DonorAssignment::query()
            ->where('donor_id', $donor->id)
            ->where('organization_id', $donor->organization_id)
            ->where('volunteer_id', $fromVolunteer->id)
            ->where('is_active', true)
            ->exists();

        if (! $ownsDonor && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'donor_id' => 'You can only transfer donors assigned to you.',
            ]);
        }

        $pendingExists = DonorTransferRequest::query()
            ->where('donor_id', $donor->id)
            ->where('status', TransferStatus::Pending)
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'donor_id' => 'A transfer is already pending for this donor.',
            ]);
        }

        $transfer = DonorTransferRequest::create([
            'organization_id' => $donor->organization_id,
            'donor_id' => $donor->id,
            'from_volunteer_id' => $fromVolunteer->id,
            'to_volunteer_id' => $toVolunteer->id,
            'requested_by' => $actor->id,
            'status' => TransferStatus::Pending,
            'reason' => $reason,
        ]);

        $this->auditLogger->log(
            'donor.transfer_requested',
            $donor,
            null,
            [
                'transfer_id' => $transfer->id,
                'to_volunteer_id' => $toVolunteer->id,
                'reason' => $reason,
            ],
            $donor->organization_id,
            $actor,
        );

        $this->notifyStakeholders($transfer, 'requested', excludeIds: [$actor->id]);

        return $transfer->load(['donor', 'fromVolunteer', 'toVolunteer']);
    }

    public function accept(DonorTransferRequest $transfer, User $actor, ?string $note = null): DonorTransferRequest
    {
        $this->assertPending($transfer);

        if ($actor->id !== $transfer->to_volunteer_id && ! $actor->isAdmin()) {
            abort(403, 'Only the receiving volunteer or an admin can accept this transfer.');
        }

        return DB::transaction(function () use ($transfer, $actor, $note) {
            $this->assignmentService->assignDonors(
                $transfer->organization_id,
                $transfer->to_volunteer_id,
                [$transfer->donor_id],
                $actor,
            );

            $transfer->donor->update([
                'was_transferred' => true,
                'last_transferred_at' => now(),
            ]);

            $transfer->update([
                'status' => TransferStatus::Accepted,
                'response_note' => $note,
                'responded_by' => $actor->id,
                'responded_at' => now(),
            ]);

            $this->auditLogger->log(
                'donor.transfer_accepted',
                $transfer->donor,
                null,
                ['transfer_id' => $transfer->id],
                $transfer->organization_id,
                $actor,
            );

            $this->notifyStakeholders($transfer->fresh(['donor', 'fromVolunteer', 'toVolunteer']), 'accepted', excludeIds: [$actor->id]);

            return $transfer->fresh(['donor', 'fromVolunteer', 'toVolunteer', 'requester', 'responder']);
        });
    }

    public function reject(DonorTransferRequest $transfer, User $actor, ?string $note = null): DonorTransferRequest
    {
        $this->assertPending($transfer);

        if ($actor->id !== $transfer->to_volunteer_id && ! $actor->isAdmin()) {
            abort(403, 'Only the receiving volunteer or an admin can reject this transfer.');
        }

        $transfer->update([
            'status' => TransferStatus::Rejected,
            'response_note' => $note,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->auditLogger->log(
            'donor.transfer_rejected',
            $transfer->donor,
            null,
            ['transfer_id' => $transfer->id],
            $transfer->organization_id,
            $actor,
        );

        $this->notifyStakeholders($transfer->fresh(['donor', 'fromVolunteer', 'toVolunteer']), 'rejected', excludeIds: [$actor->id]);

        return $transfer->fresh(['donor', 'fromVolunteer', 'toVolunteer', 'requester', 'responder']);
    }

    public function cancel(DonorTransferRequest $transfer, User $actor): DonorTransferRequest
    {
        $this->assertPending($transfer);

        if ($actor->id !== $transfer->requested_by && $actor->id !== $transfer->from_volunteer_id && ! $actor->isAdmin()) {
            abort(403, 'Only the requester or an admin can cancel this transfer.');
        }

        $transfer->update([
            'status' => TransferStatus::Cancelled,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->notifyStakeholders($transfer->fresh(['donor', 'fromVolunteer', 'toVolunteer']), 'cancelled', excludeIds: [$actor->id]);

        return $transfer->fresh();
    }

    protected function assertPending(DonorTransferRequest $transfer): void
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages([
                'transfer' => 'This transfer is no longer pending.',
            ]);
        }
    }

    /**
     * Notify only people directly involved (from / to / requester) — not all admins.
     *
     * @param  list<int>  $excludeIds
     */
    protected function notifyStakeholders(DonorTransferRequest $transfer, string $event, array $excludeIds = []): void
    {
        $recipients = collect([
            $transfer->toVolunteer,
            $transfer->fromVolunteer,
            $transfer->requester,
        ])
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => in_array($user->id, $excludeIds, true))
            ->values();

        Notification::send($recipients, new DonorTransferNotification($transfer, $event));
    }
}
