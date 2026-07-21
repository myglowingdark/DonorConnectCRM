<?php

namespace App\Services\Commissions;

use App\Enums\AttributionStatus;
use App\Enums\CommissionCycleStatus;
use App\Models\CommissionCycle;
use App\Models\CommissionLineItem;
use App\Models\CommissionSetting;
use App\Models\DonationAttribution;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function settingsFor(int $organizationId): CommissionSetting
    {
        return CommissionSetting::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'individual_enabled' => true,
                'individual_default_percent' => 5,
                'shared_enabled' => false,
                'shared_percent' => 0,
                'shared_eligibility' => 'active_contributors',
                'volunteer_overrides' => [],
            ]
        );
    }

    public function calculate(int $organizationId, string $period, User $actor): CommissionCycle
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw ValidationException::withMessages([
                'period' => 'Period must be YYYY-MM.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $settings = $this->settingsFor($organizationId);

        $attributions = DonationAttribution::query()
            ->forOrganization($organizationId)
            ->where('status', AttributionStatus::Approved)
            ->whereHas('donation', fn ($q) => $q->whereBetween('donated_at', [$start, $end]))
            ->with('donation')
            ->get();

        $byVolunteer = [];
        $verifiedTotal = 0.0;

        foreach ($attributions as $attr) {
            $vid = (int) $attr->volunteer_id;
            $amount = (float) ($attr->donation?->amount ?? 0);
            $verifiedTotal += $amount;
            $byVolunteer[$vid] = ($byVolunteer[$vid] ?? 0) + $amount;
        }

        $sharedPool = 0.0;
        if ($settings->shared_enabled && $settings->shared_percent > 0) {
            $sharedPool = round($verifiedTotal * ((float) $settings->shared_percent) / 100, 2);
        }

        $contributorCount = count(array_filter($byVolunteer, fn ($t) => $t > 0));
        $sharedEach = ($sharedPool > 0 && $contributorCount > 0)
            ? round($sharedPool / $contributorCount, 2)
            : 0.0;

        return DB::transaction(function () use (
            $organizationId,
            $period,
            $settings,
            $byVolunteer,
            $verifiedTotal,
            $sharedPool,
            $sharedEach,
            $actor,
        ) {
            $cycle = CommissionCycle::query()->firstOrNew([
                'organization_id' => $organizationId,
                'period' => $period,
            ]);

            if ($cycle->exists && $cycle->status === CommissionCycleStatus::Paid) {
                throw ValidationException::withMessages([
                    'period' => 'Cannot recalculate a paid cycle.',
                ]);
            }

            $cycle->fill([
                'status' => CommissionCycleStatus::Draft,
                'verified_donation_total' => $verifiedTotal,
                'shared_pool' => $sharedPool,
                'approved_at' => null,
                'paid_at' => null,
            ]);
            $cycle->save();

            $cycle->lineItems()->delete();

            $individualTotal = 0.0;
            $payableTotal = 0.0;

            foreach ($byVolunteer as $volunteerId => $attributed) {
                $rate = $settings->individual_enabled
                    ? $settings->rateForVolunteer((int) $volunteerId)
                    : 0.0;
                $individual = $settings->individual_enabled
                    ? round($attributed * $rate / 100, 2)
                    : 0.0;
                $shared = $attributed > 0 ? $sharedEach : 0.0;
                $final = round($individual + $shared, 2);

                CommissionLineItem::create([
                    'commission_cycle_id' => $cycle->id,
                    'organization_id' => $organizationId,
                    'volunteer_id' => $volunteerId,
                    'attributed_donation_total' => $attributed,
                    'individual_rate' => $rate,
                    'individual_commission' => $individual,
                    'shared_allocation' => $shared,
                    'adjustments' => 0,
                    'final_payable' => $final,
                    'status' => CommissionCycleStatus::Draft->value,
                ]);

                $individualTotal += $individual;
                $payableTotal += $final;
            }

            $cycle->update([
                'individual_total' => $individualTotal,
                'payable_total' => $payableTotal,
            ]);

            $this->auditLogger->log(
                'commission.cycle_calculated',
                $cycle,
                null,
                ['period' => $period, 'payable_total' => $payableTotal],
                $organizationId,
                $actor,
            );

            return $cycle->fresh(['lineItems.volunteer']);
        });
    }

    public function approve(CommissionCycle $cycle, User $actor): CommissionCycle
    {
        if ($cycle->status !== CommissionCycleStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft cycles can be approved.',
            ]);
        }

        $cycle->update([
            'status' => CommissionCycleStatus::Approved,
            'approved_at' => now(),
        ]);
        $cycle->lineItems()->update(['status' => CommissionCycleStatus::Approved->value]);

        $this->auditLogger->log(
            'commission.cycle_approved',
            $cycle,
            null,
            ['period' => $cycle->period],
            $cycle->organization_id,
            $actor,
        );

        return $cycle->fresh(['lineItems.volunteer']);
    }

    public function markPaid(CommissionCycle $cycle, User $actor): CommissionCycle
    {
        if ($cycle->status !== CommissionCycleStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only approved cycles can be marked paid.',
            ]);
        }

        $cycle->update([
            'status' => CommissionCycleStatus::Paid,
            'paid_at' => now(),
        ]);
        $cycle->lineItems()->update(['status' => CommissionCycleStatus::Paid->value]);

        $this->auditLogger->log(
            'commission.cycle_paid',
            $cycle,
            null,
            ['period' => $cycle->period],
            $cycle->organization_id,
            $actor,
        );

        return $cycle->fresh(['lineItems.volunteer']);
    }
}
