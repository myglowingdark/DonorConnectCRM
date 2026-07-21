<?php

namespace App\Services\Donors;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\User;
use App\Notifications\DonorAssignedNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<int>  $donorIds
     */
    public function assignDonors(
        int $organizationId,
        int $volunteerId,
        array $donorIds,
        User $actor,
        bool $notify = true,
    ): int {
        $volunteer = User::query()->findOrFail($volunteerId);

        if (! $volunteer->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([
                'volunteer_id' => 'Volunteer is not assigned to this organization.',
            ]);
        }

        $donors = Donor::query()
            ->forOrganization($organizationId)
            ->whereIn('id', $donorIds)
            ->get();

        if ($donors->count() !== count(array_unique($donorIds))) {
            throw ValidationException::withMessages([
                'donor_ids' => 'One or more donors do not belong to this organization.',
            ]);
        }

        return DB::transaction(function () use ($organizationId, $volunteerId, $volunteer, $donors, $actor, $notify) {
            $count = 0;

            foreach ($donors as $donor) {
                DonorAssignment::query()
                    ->where('donor_id', $donor->id)
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                DonorAssignment::updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'donor_id' => $donor->id,
                        'volunteer_id' => $volunteerId,
                    ],
                    [
                        'assigned_by' => $actor->id,
                        'assigned_at' => now(),
                        'is_active' => true,
                    ]
                );

                $this->auditLogger->log(
                    'donor.assigned',
                    $donor,
                    null,
                    ['volunteer_id' => $volunteerId],
                    $organizationId,
                    $actor,
                );

                $count++;
            }

            if ($notify && $count > 0 && $volunteerId !== $actor->id) {
                Notification::send(
                    $volunteer,
                    new DonorAssignedNotification($organizationId, $count, $actor, 'assigned')
                );
            }

            return $count;
        });
    }

    public function unassignDonors(int $organizationId, array $donorIds, User $actor): int
    {
        $assignments = DonorAssignment::query()
            ->forOrganization($organizationId)
            ->whereIn('donor_id', $donorIds)
            ->where('is_active', true)
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['is_active' => false]);
            $this->auditLogger->log(
                'donor.unassigned',
                $assignment->donor,
                ['volunteer_id' => $assignment->volunteer_id],
                null,
                $organizationId,
                $actor,
            );
        }

        return $assignments->count();
    }

    /**
     * Fairly distribute donors across volunteers, optionally capped and limited to a donor set.
     *
     * @param  array<int>  $volunteerIds
     * @param  array<int>|null  $donorIds  When set, only these donors (still unassigned/forced) are distributed.
     */
    public function distributeEquallyWithCap(
        int $organizationId,
        array $volunteerIds,
        User $actor,
        ?int $capPerVolunteer = null,
        ?array $donorIds = null,
        bool $notifyAssignees = true,
        string $notifyEvent = 'imported',
    ): int {
        $volunteers = User::query()
            ->whereIn('id', $volunteerIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->belongsToOrganization($organizationId) && $u->isVolunteer())
            ->values();

        if ($volunteers->isEmpty()) {
            throw ValidationException::withMessages([
                'volunteer_ids' => 'Select at least one active volunteer in this organization.',
            ]);
        }

        $query = Donor::query()->forOrganization($organizationId)->orderBy('id');

        if ($donorIds !== null) {
            $query->whereIn('id', $donorIds);
        } else {
            $query->whereDoesntHave('assignments', fn ($q) => $q->where('is_active', true));
        }

        $donors = $query->get();

        if ($donors->isEmpty()) {
            return 0;
        }

        $workload = $this->workloadCounts($organizationId);

        return DB::transaction(function () use (
            $organizationId,
            $donors,
            $volunteers,
            $actor,
            $capPerVolunteer,
            $workload,
            $notifyAssignees,
            $notifyEvent,
        ) {
            $count = 0;
            $assignedCounts = $volunteers->mapWithKeys(
                fn (User $v) => [$v->id => (int) ($workload[$v->id] ?? 0)]
            )->all();
            $batchCounts = $volunteers->mapWithKeys(fn (User $v) => [$v->id => 0])->all();

            foreach ($donors as $donor) {
                $eligible = $volunteers->filter(function (User $volunteer) use ($assignedCounts, $capPerVolunteer) {
                    if ($capPerVolunteer === null) {
                        return true;
                    }

                    return ($assignedCounts[$volunteer->id] ?? 0) < $capPerVolunteer;
                })->values();

                if ($eligible->isEmpty()) {
                    break;
                }

                /** @var User $pick */
                $pick = $eligible->sortBy(fn (User $v) => $assignedCounts[$v->id] ?? 0)->first();

                $this->assignDonors($organizationId, $pick->id, [$donor->id], $actor, notify: false);
                $assignedCounts[$pick->id] = ($assignedCounts[$pick->id] ?? 0) + 1;
                $batchCounts[$pick->id] = ($batchCounts[$pick->id] ?? 0) + 1;
                $count++;
            }

            if ($notifyAssignees) {
                foreach ($batchCounts as $volunteerId => $n) {
                    if ($n < 1 || $volunteerId === $actor->id) {
                        continue;
                    }
                    $volunteer = $volunteers->firstWhere('id', $volunteerId);
                    if ($volunteer) {
                        Notification::send(
                            $volunteer,
                            new DonorAssignedNotification($organizationId, $n, $actor, $notifyEvent)
                        );
                    }
                }
            }

            return $count;
        });
    }

    /**
     * Fairly distribute unassigned donors across active volunteers.
     *
     * @param  array<int>  $volunteerIds
     */
    public function distributeEqually(int $organizationId, array $volunteerIds, User $actor, ?int $capPerVolunteer = null): int
    {
        return $this->distributeEquallyWithCap($organizationId, $volunteerIds, $actor, $capPerVolunteer);
    }

    public function workloadCounts(int $organizationId): Collection
    {
        return DonorAssignment::query()
            ->forOrganization($organizationId)
            ->where('is_active', true)
            ->selectRaw('volunteer_id, COUNT(*) as donor_count')
            ->groupBy('volunteer_id')
            ->pluck('donor_count', 'volunteer_id');
    }
}
