<?php

namespace App\Services\Donors;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<int>  $donorIds
     */
    public function assignDonors(int $organizationId, int $volunteerId, array $donorIds, User $actor): int
    {
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

        return DB::transaction(function () use ($organizationId, $volunteerId, $donors, $actor) {
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
     * Fairly distribute unassigned donors across active volunteers.
     *
     * @param  array<int>  $volunteerIds
     */
    public function distributeEqually(int $organizationId, array $volunteerIds, User $actor): int
    {
        $volunteers = User::query()
            ->whereIn('id', $volunteerIds)
            ->get()
            ->filter(fn (User $u) => $u->belongsToOrganization($organizationId))
            ->values();

        if ($volunteers->isEmpty()) {
            throw ValidationException::withMessages([
                'volunteer_ids' => 'Select at least one active volunteer in this organization.',
            ]);
        }

        $unassigned = Donor::query()
            ->forOrganization($organizationId)
            ->whereDoesntHave('assignments', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();

        if ($unassigned->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($organizationId, $unassigned, $volunteers, $actor) {
            $count = 0;
            $volunteerCount = $volunteers->count();

            foreach ($unassigned->values() as $index => $donor) {
                /** @var User $volunteer */
                $volunteer = $volunteers[$index % $volunteerCount];
                $this->assignDonors($organizationId, $volunteer->id, [$donor->id], $actor);
                $count++;
            }

            return $count;
        });
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
