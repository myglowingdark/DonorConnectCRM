<?php

namespace App\Services\Donors;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorHandover;
use App\Models\DonorInteraction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverService
{
    public function __construct(
        private AssignmentService $assignmentService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     from_volunteer_id: int,
     *     to_volunteer_ids: array<int>,
     *     mode: string,
     *     donor_ids?: array<int>,
     *     reassign_interactions?: bool,
     *     notes?: string|null,
     *     cap_per_volunteer?: int|null
     * }  $data
     */
    public function handover(int $organizationId, User $actor, array $data): DonorHandover
    {
        $fromId = (int) $data['from_volunteer_id'];
        $toIds = array_values(array_unique(array_map('intval', $data['to_volunteer_ids'] ?? [])));
        $mode = $data['mode'] === 'partial' ? 'partial' : 'full';
        $reassignInteractions = (bool) ($data['reassign_interactions'] ?? false);
        $cap = isset($data['cap_per_volunteer']) ? (int) $data['cap_per_volunteer'] : null;

        $from = User::query()->findOrFail($fromId);
        if (! $from->belongsToOrganization($organizationId) || ! $from->isVolunteer()) {
            throw ValidationException::withMessages([
                'from_volunteer_id' => 'Source must be a volunteer in this organization.',
            ]);
        }

        $recipients = User::query()
            ->whereIn('id', $toIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->belongsToOrganization($organizationId) && $u->isVolunteer() && $u->id !== $fromId)
            ->values();

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'to_volunteer_ids' => 'Select at least one different active volunteer to receive the handover.',
            ]);
        }

        $donorQuery = Donor::query()
            ->forOrganization($organizationId)
            ->assignedTo($fromId);

        if ($mode === 'partial') {
            $donorIds = array_map('intval', $data['donor_ids'] ?? []);
            if ($donorIds === []) {
                throw ValidationException::withMessages([
                    'donor_ids' => 'Select donors for a partial handover.',
                ]);
            }
            $donorQuery->whereIn('id', $donorIds);
        }

        $donors = $donorQuery->orderBy('id')->get();
        if ($donors->isEmpty()) {
            throw ValidationException::withMessages([
                'from_volunteer_id' => 'No assigned donors found to hand over.',
            ]);
        }

        return DB::transaction(function () use (
            $organizationId,
            $actor,
            $from,
            $recipients,
            $donors,
            $mode,
            $reassignInteractions,
            $cap,
            $data,
        ) {
            // Temporarily unassign so redistribute can place them.
            DonorAssignment::query()
                ->forOrganization($organizationId)
                ->where('volunteer_id', $from->id)
                ->whereIn('donor_id', $donors->pluck('id'))
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $moved = $this->assignmentService->distributeEquallyWithCap(
                $organizationId,
                $recipients->pluck('id')->all(),
                $actor,
                $cap,
                $donors->pluck('id')->all(),
            );

            $interactionsMoved = 0;
            if ($reassignInteractions) {
                // Round-robin reassign historical interactions to receiving volunteers.
                $interactions = DonorInteraction::query()
                    ->forOrganization($organizationId)
                    ->where('volunteer_id', $from->id)
                    ->whereIn('donor_id', $donors->pluck('id'))
                    ->orderBy('id')
                    ->get();

                $recipientCount = $recipients->count();
                foreach ($interactions->values() as $index => $interaction) {
                    $to = $recipients[$index % $recipientCount];
                    $interaction->update(['volunteer_id' => $to->id]);
                    $interactionsMoved++;
                }
            }

            $handover = DonorHandover::create([
                'organization_id' => $organizationId,
                'from_volunteer_id' => $from->id,
                'initiated_by' => $actor->id,
                'mode' => $mode,
                'donors_moved' => $moved,
                'reassign_interactions' => $reassignInteractions,
                'interactions_moved' => $interactionsMoved,
                'notes' => $data['notes'] ?? null,
                'to_volunteer_ids' => $recipients->pluck('id')->all(),
                'donor_ids' => $donors->pluck('id')->all(),
            ]);

            $this->auditLogger->log(
                'volunteer.handover',
                $from,
                null,
                [
                    'handover_id' => $handover->id,
                    'donors_moved' => $moved,
                    'to_volunteer_ids' => $recipients->pluck('id')->all(),
                    'mode' => $mode,
                ],
                $organizationId,
                $actor,
            );

            return $handover;
        });
    }
}
